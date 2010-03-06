
<!-- indexer::stop -->
<div class="<?php echo $this->class; ?> block"<?php echo $this->cssID; ?><?php if ($this->style): ?> style="<?php echo $this->style; ?>"<?php endif; ?>>
<?php if ($this->headline): ?>

<<?php echo $this->hl; ?>><?php echo $this->headline; ?></<?php echo $this->hl; ?>>
<?php endif; ?>

<form action="<?php echo $this->action; ?>" method="post">
<div class="formbody">
<?php if ($this->message): ?>
<p class="<?php echo $this->mclass; ?>"><?php echo $this->message; ?></p>
<?php endif; ?>
<input type="hidden" name="FORM_SUBMIT" value="<?php echo $this->formId; ?>" />
<?php if (!$this->showChannels): ?>
<?php foreach ($this->channels as $id=>$title): ?>
<input type="hidden" name="channels[]" value="<?php echo $id; ?>" />
<?php endforeach; ?>
<?php endif; ?>
<table cellspacing="0" cellpadding="0" summary="">
<?php echo $this->fields; ?>
<?php if ($this->showChannels): ?>
  <tr class="<?php echo $this->rowChannels; ?>">
    <td class="col_0 col_first">&nbsp;</td>
    <td class="col_1 col_last"><div class="checkbox_container">
<?php foreach ($this->channels as $id=>$title): ?>
<span><input type="checkbox" name="channels[]" id="opt_<?php echo $this->id; ?>_<?php echo $id; ?>" value="<?php echo $id; ?>" class="checkbox" /> <label for="opt_<?php echo $this->id; ?>_<?php echo $id; ?>"><?php echo $title; ?></label></span>
<?php endforeach; ?>
</div></td>
  </tr>
<?php endif; ?>
  <tr class="<?php echo $this->rowSubmit; ?> row_last">
    <td class="col_0 col_first">&nbsp;</td>
    <td class="col_1 col_last"><div class="submit_container"><input type="submit" name="submit" class="submit" value="<?php echo $this->submit; ?>" /></div></td>
  </tr>
</table>
</div>
</form>

</div>
<!-- indexer::continue -->
