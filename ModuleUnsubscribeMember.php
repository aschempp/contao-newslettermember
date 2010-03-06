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


class ModuleUnsubscribeMember extends ModuleUnsubscribe
{

	public function generate()
	{
		if (TL_MODE == 'BE')
		{
			$objTemplate = new BackendTemplate('be_wildcard');

			$objTemplate->wildcard = '### NEWSLETTER UNSUBSCRIBE MEMBER ###';
			$objTemplate->title = $this->headline;
			$objTemplate->id = $this->id;
			$objTemplate->link = $this->name;
			$objTemplate->href = 'typolight/main.php?do=modules&amp;act=edit&amp;id=' . $this->id;

			return $objTemplate->parse();
		}
		
		return parent::generate();
	}
	
	
	/**
	 * Add a new recipient
	 */
	protected function removeRecipient()
	{
		$arrChannels = $this->Input->post('channels');

		// Check selection
		if (!is_array($arrChannels) || count($arrChannels) < 1)
		{
			$_SESSION['UNSUBSCRIBE_ERROR'] = $GLOBALS['TL_LANG']['ERR']['noChannels'];
			$this->reload();
		}

		// Validate e-mail address
		if (!preg_match('/^\w+([!#\$%&\'\*\+\-\/=\?^_`\.\{\|\}~]*\w+)*@\w+([_\.-]*\w+)*\.[a-z]{2,6}$/i', $this->Input->post('email', true)))
		{
			$_SESSION['UNSUBSCRIBE_ERROR'] = $GLOBALS['TL_LANG']['ERR']['email'];
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

		$arrRemove = array_intersect($arrChannels, $arrSubscriptions);

		// Return if there are no subscriptions to remove
		if (!is_array($arrRemove) || count($arrRemove) < 1)
		{
			$_SESSION['UNSUBSCRIBE_ERROR'] = $GLOBALS['TL_LANG']['ERR']['unsubscribed'];
			$this->reload();
		}

		// Remove subscriptions
		$this->Database->prepare("DELETE FROM tl_newsletter_recipients WHERE email=? AND pid IN(" . implode(',', $arrRemove) . ")")
					   ->execute($this->Input->post('email', true));
					   
		// Remove member
		$arrRetain = array_diff($arrSubscriptions, $arrChannels);
		if (count($arrRetain))
		{
			$this->Database->prepare("UPDATE tl_member SET newsletter=? WHERE email=?")->execute(serialize($arrRetain), $this->Input->post('email'));
		}
		else
		{
			// Delete only if member groups match
			$this->Database->prepare("DELETE FROM tl_member WHERE email=? AND groups=?")->execute($this->Input->post('email'), $this->reg_groups);
		}

		// Confirmation e-mail
		$objEmail = new Email();

		// Get channels
		$objChannel = $this->Database->execute("SELECT title FROM tl_newsletter_channel WHERE id IN(" . implode(',', $arrChannels) . ")");

		$strText = str_replace('##domain##', $this->Environment->host, $this->nl_unsubscribe);
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

		$_SESSION['UNSUBSCRIBE_CONFIRM'] = $GLOBALS['TL_LANG']['MSC']['nl_removed'];
		$this->reload();
	}
}

