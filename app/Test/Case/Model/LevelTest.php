<?php
App::uses('Level', 'Model');
App::uses('Rating', 'Model');
class LevelTest extends CakeTestCase {
  public function setUp() {
    parent::setUp();
    $this->Level = ClassRegistry::init('Level');
    $this->Level->deleteAll(true);
    $this->Rating = ClassRegistry::init('Rating');
    $this->Rating->deleteAll(true);
  }

  public function testRating() {
    $this->Level->create();
    $this->assertEquals(0, $this->Level->field('rating'));

    $this->Level->rate(1, -1);
    $this->assertEquals(-1, $this->Level->field('rating'));

    $this->Level->rate(1, 1);
    $this->assertEquals(1, $this->Level->field('rating'));

    $this->Level->rate(2, 1);
    $this->assertEquals(2, $this->Level->field('rating'));
  }
}
?>
