<?php if (!defined('TL_ROOT')) die('You can not access this file directly!');

/**
 * TYPOlight webCMS
 * Copyright (C) 2005-2009 Leo Feyer
 *
 * This program is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation, either
 * version 2.1 of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public
 * License along with this program. If not, please visit the Free
 * Software Foundation website at http://www.gnu.org/licenses/.
 *
 * PHP version 5
 * @copyright  Andreas Schempp 2009
 * @author     Andreas Schempp <andreas@schempp.ch
 * @license    http://opensource.org/licenses/lgpl-3.0.html
 * @version    $Id$
 */


class ModuleSubscribeMember extends ModuleSubscribe
{

	public function generate()
	{
		if (TL_MODE == 'BE')
		{
			$objTemplate = new BackendTemplate('be_wildcard');

			$objTemplate->wildcard = '### NEWSLETTER SUBSCRIBE MEMBER ###';
			$objTemplate->title = $this->headline;
			$objTemplate->id = $this->id;
			$objTemplate->link = $this->name;
			$objTemplate->href = 'typolight/main.php?do=modules&amp;act=edit&amp;id=' . $this->id;

			return $objTemplate->parse();
		}
		
		$this->editable = deserialize($this->editable);

		// Return if there are no editable fields
		if (!is_array($this->editable) || count($this->editable) < 1)
		{
			return '';
		}
		
		return parent::generate();
	}
	
	
	protected function compile()
	{
		parent::compile();
		
		$this->Template->formId = 'tl_subscribe_member';
		
		$this->loadLanguageFile('tl_member');
		$this->loadDataContainer('tl_member');
		
		// E-Mail is always mandatory
		$GLOBALS['TL_DCA']['tl_member']['fields']['email']['eval']['mandatory'] = true;
		
		$this->Template->fields = '';
		$doNotSubmit = false;
		
		$arrMember = array();
		$i = 0;
		
		// Build form
		foreach ($this->editable as $field)
		{
			$arrData = $GLOBALS['TL_DCA']['tl_member']['fields'][$field];
			$strClass = $GLOBALS['TL_FFL'][$arrData['inputType']];

			// Continue if the class is not defined
			if (!$this->classFileExists($strClass))
			{
				continue;
			}

			$objWidget = new $strClass($this->prepareForWidget($arrData, $field));

			$objWidget->storeValues = true;
			$objWidget->rowClass = 'row_' . $i . (($i == 0) ? ' row_first' : '') . ((($i % 2) == 0) ? ' even' : ' odd');


			// Validate input
			if ($this->Input->post('FORM_SUBMIT') == 'tl_subscribe_member')
			{
				$objWidget->validate();
				$varValue = $objWidget->value;

/*
				// Check whether the password matches the username
				if ($objWidget instanceof FormPassword && $varValue == $this->Input->post('username'))
				{
					$objWidget->addError($GLOBALS['TL_LANG']['ERR']['passwordName']);
				}
*/

				// Convert date formats into timestamps
				if (strlen($varValue) && in_array($arrData['eval']['rgxp'], array('date', 'time', 'datim')))
				{
					$objDate = new Date($varValue, $GLOBALS['TL_CONFIG'][$arrData['eval']['rgxp'] . 'Format']);
					$varValue = $objDate->tstamp;
				}

/*
				// Make sure that unique fields are unique
				if ($GLOBALS['TL_DCA']['tl_member']['fields'][$field]['eval']['unique'])
				{
					$objUnique = $this->Database->prepare("SELECT * FROM tl_member WHERE " . $field . "=?")
												->limit(1)
												->execute($varValue);

					if ($objUnique->numRows)
					{
						$objWidget->addError(sprintf($GLOBALS['TL_LANG']['ERR']['unique'], (strlen($arrData['label'][0]) ? $arrData['label'][0] : $field)));
					}
				}
*/

				if ($objWidget->hasErrors())
				{
					$doNotSubmit = true;
				}

				// Store current value
				elseif ($objWidget->submitInput())
				{
					$arrMember[$field] = $varValue;
				}
			}

/*
			if ($objWidget instanceof uploadable)
			{
				$hasUpload = true;
			}
*/

			$this->Template->fields .= $objWidget->parse();

			++$i;
		}
		
		if ($this->Template->showChannels)
		{
			$this->Template->rowChannels = 'row_' . $i . ((($i % 2) == 0) ? ' even' : ' odd');
			$i++;
		}
		
		$this->Template->rowSubmit = 'row_' . $i . ((($i % 2) == 0) ? ' even' : ' odd');
		
		// Subscribe
		if ($this->Input->post('FORM_SUBMIT') == 'tl_subscribe_member' && !$doNotSubmit)
		{
			$this->addMember($arrMember);
		}
	}
	
	
	/**
	 * Add a new recipient
	 */
	protected function addMember($arrMember)
	{
		$arrChannels = $this->Input->post('channels');

		// Check selection
		if (!is_array($arrChannels) || count($arrChannels) < 1)
		{
			$_SESSION['SUBSCRIBE_ERROR'] = $GLOBALS['TL_LANG']['ERR']['noChannels'];
			$this->reload();
		}

		// Validate e-mail address
		if (!preg_match('/^\w+([!#\$%&\'\*\+\-\/=\?^_`\.\{\|\}~]*\w+)*@\w+([_\.-]*\w+)*\.[a-z]{2,6}$/i', $this->Input->post('email', true)))
		{
			$_SESSION['SUBSCRIBE_ERROR'] = $GLOBALS['TL_LANG']['ERR']['email'];
			$this->reload();
		}

		$arrSubscriptions = array();

		// Get active subscriptions
		$objSubscription = $this->Database->prepare("SELECT pid FROM tl_newsletter_recipients WHERE email=? AND active=?")
										  ->execute($this->Input->post('email', true), 1);

		if ($objSubscription->numRows)
		{
			$arrSubscriptions = $objSubscription->fetchEach('pid');
		}

		$arrNew = array_diff($arrChannels, $arrSubscriptions);

		// Return if there are no new subscriptions
		if (!is_array($arrNew) || count($arrNew) < 1)
		{
			$_SESSION['SUBSCRIBE_ERROR'] = $GLOBALS['TL_LANG']['ERR']['subscribed'];
			$this->reload();
		}

		$time = time();
		$strToken = md5(uniqid('', true));
		$arrCondition = array();
		$arrValues = array();

		// Prepare new subscriptions
		foreach ($arrNew as $id)
		{
			$arrValues[] = $id;
			$arrValues[] = $time;
			$arrValues[] = $this->Input->post('email', true);
			$arrValues[] = '';
			$arrValues[] = $time;
			$arrValues[] = $this->Environment->ip;
			$arrValues[] = $strToken;

			$arrCondition[] = '(?, ?, ?, ?, ?, ?, ?)';
		}

		// Remove old subscriptions that have not been activated yet
		$this->Database->prepare("DELETE FROM tl_newsletter_recipients WHERE email=? AND active!=?")
					   ->execute($this->Input->post('email', true), 1);

		// Add new subscriptions
		$this->Database->prepare("INSERT INTO tl_newsletter_recipients (pid, tstamp, email, active, addedOn, ip, token) VALUES " . implode(', ', $arrCondition))
					   ->execute($arrValues);

		// Add member
		$arrMember['newsletter'] = $arrChannels;
		$objMember = $this->Database->prepare("SELECT * FROM tl_member WHERE email=?")->limit(1)->execute($arrMember['email']);
		if ($objMember->numRows)
		{
			if ($objMember->groups == $this->reg_groups)
			{
				$this->Database->prepare("UPDATE tl_member %s WHERE email=?")->set($arrMember)->execute($arrMember['email']);
			}
		}
		else
		{
			$arrMember['groups'] = $this->reg_groups;
			$this->Database->prepare("INSERT INTO tl_member %s")->set($arrMember)->execute();
		}

		// Activation e-mail
		$objEmail = new Email();

		// Get channels
		$objChannel = $this->Database->execute("SELECT title FROM tl_newsletter_channel WHERE id IN(" . implode(',', $arrChannels) . ")");

		$strText = str_replace('##domain##', $this->Environment->host, $this->nl_subscribe);
		$strText = str_replace('##link##', $this->Environment->base . $this->Environment->request . (($GLOBALS['TL_CONFIG']['disableAlias'] || strpos($this->Environment->request, '?') !== false) ? '&' : '?') . 'token=' . $strToken, $strText);
		$strText = str_replace(array('##channel##', '##channels##'), implode("\n", $objChannel->fetchEach('title')), $strText);

		$objEmail->from = $GLOBALS['TL_ADMIN_EMAIL'];
		$objEmail->subject = sprintf($GLOBALS['TL_LANG']['MSC']['nl_subject'], $this->Environment->host);
		$objEmail->text = $strText;

		$objEmail->sendTo($this->Input->post('email', true));
		global $objPage;

		// Redirect to jumpTo page
		if (strlen($this->jumpTo) && $this->jumpTo != $objPage->id)
		{
			$objNextPage = $this->Database->prepare("SELECT id, alias FROM tl_page WHERE id=?")
										  ->limit(1)
										  ->execute($this->jumpTo);

			if ($objNextPage->numRows)
			{
				$this->redirect($this->generateFrontendUrl($objNextPage->fetchAssoc()));
			}
		}

		$_SESSION['SUBSCRIBE_CONFIRM'] = $GLOBALS['TL_LANG']['MSC']['nl_confirm'];
		$this->reload();
	}
}

