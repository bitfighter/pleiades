<?php
echo $this->Form->create('Level');
echo $this->Form->input('name');
echo $this->Form->input('content');
echo $this->Form->input('levelgen_basename');
echo $this->Form->input('levelgen');
echo $this->Form->end('Upload Level');
?>