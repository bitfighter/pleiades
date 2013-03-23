<?php echo $this->Html->tag('h1', $level['Level']['name']  . " by " . $level['User']['username'], array('escape' => true)); ?>

<?php
echo $this->element('rating', array(
  'id' => $level['Level']['id'],
  'rating' => $level['Level']['rating'],
  'current_rating' => $current_rating
));
?>

<?php
echo $this->Html->link('Download', array(
  'action' => 'download',
  $level['Level']['id']
));
if($is_owner) {
  echo '&nbsp;';
  echo $this->Html->link('Edit', array(
    'action' => 'edit',
    $level['Level']['id']
  ));
}
?>
<?php echo $this->Html->tag('pre', $level['Level']['content'], array('escape' => true)) ?>
<?php 
if (!empty($level['Level']['levelgen'])) {
  echo $this->Html->tag('h2', $level['Level']['levelgen_basename'] . '.levelgen', array('escape' => true));
  echo $this->Html->tag('pre', $level['Level']['levelgen'], array('escape' => true));
}
?>
