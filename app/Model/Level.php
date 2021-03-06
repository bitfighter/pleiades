<?php
class Level extends AppModel {
    public $actsAs = array('Search.Searchable');
    public $filterArgs = array(
            'name' => array('type' => 'like'),
            'game_type' => array('type' => 'like'),
            'author' => array('type' => 'like'),
            'tags' => array('type' => 'subquery', 'method' => 'findByTags', 'field' => 'Level.id'),
            'range' => array('type' => 'expression', 'method' => 'makeRangeCondition', 'field' => 'Levels.rating BETWEEN ? AND ?'),
    );

    public $uses = array('User', 'Rating');
    public $belongsTo = array('User' => array('fields' => array('username', 'user_id')));
    public $hasAndBelongsToMany = array('Tag');
    public $validate = array(
            'content' => array(
                    'levelgenExistence' => array(
                            'allowEmpty' => true,
                            'rule' => 'levelgenExistence',
                            'message' => 'If your level specifies a script it must also include a levelgen, otherwise the levelgen must be empty',
                    )
            )
    );

    /**
     * Converts $text into a file name with the given extension using the
     * following rules:
     *  - anything which is not a letter, space, or underscore is removed
     *  - all letters are made lowercase
     *  - both spaces and underscores are removed from the beginning and end
     *  - groups of spaces or underscores with length > 0 are changed to underscores
     *  - the appropriate extension is appended
     */
    public static function stringToFileName($text, $extension = '.level') {
        $text = preg_replace('/[^a-zA-Z _]+/', '', $text);
        $text = preg_replace('/^[ _]+/', '', $text);
        $text = preg_replace('/[ _]+$/', '', $text);
        $text = strtolower($text);
        $text = preg_replace('/[ _]+/', '_', $text);
        $text .= $extension;
        return $text;
    }


    // checks that files have levelgens IFF the level has a script line and sets
    // the levelgen filename as needed
    public function levelgenExistence($check) {
        $scripts = preg_grep('/^\s*Script\s.*$/', split("\n", $this->data['Level']['content']));
        $parts = preg_split('/\s+/', array_shift($scripts));

        // if the Script line with one or more arguments is found, require a levelgen
        if(array_key_exists(1, $parts) && trim($parts[1]) != '') {
            $fileName = trim($parts[1]);
            $fileName = preg_replace('/\.levelgen$/i', '', $fileName);
            $this->data['Level']['levelgen_filename'] = $fileName . '.levelgen';
            $this->data['Level']['levelgen'] = trim($this->data['Level']['levelgen']);
            return !empty($this->data['Level']['levelgen']);
        } else {
            return empty($this->data['Level']['levelgen']);
        }
    }

    public function beforeValidate($options = array()) {
        if(!empty($this->data['Level']['content'])) {
            // content must have a LevelName line
            $match = array();
            preg_match("/\s*LevelName\s+([^\r\n]*)/", $this->data['Level']['content'], $match);
            if(count($match) < 2) {
                array_push($this->validationErrors, 'You must include a LevelName');
                return false;
            }

            $name = $match[1];
            $name = preg_replace('/"/', '', $name);
            if(empty($name))
            {
                $name = "Untitled";
            }
            $this->set('name', $name);

            // try to parse the gametype
            $match = array();
            preg_match("/\s*(\w*)GameType/", $this->data['Level']['content'], $match);
            $match[1] = empty($match[1]) ? "" : $match[1];
            if($match[1] == "Hunters") {
                $match[1] = "Nexus";
            }
            $prettyNames = Configure::read('App.gametype_prefix_to_pretty_name_map');
            if(isset($prettyNames[$match[1]])) {
                $this->set('game_type', $prettyNames[$match[1]]);
            } else {
                $this->set('game_type', 'Bit Match');
            }

            // setting the author will affect the filename
            $prefix = '';
            if(!empty($this->data['Level']['author'])) {
                $prefix = Level::stringToFileName($this->data['Level']['author'], '');
            } else if(isset($this->data['Level']['user_id'])) {
                $user = $this->User->findByUserId($this->data['Level']['user_id']);
                $prefix = Level::stringToFileName($user['User']['username'], '');
            }

            $levelFilename = $prefix . (!empty($prefix) ? '_' : '') . Level::stringToFileName($name, '.level');
            $result = $this->findByLevelFilename($levelFilename);

            if($result && (!isset($this->id) || $this->id != $result['Level']['id'])) {
                array_push($this->validationErrors, 'The name "' . $this->data['Level']['name'] . '" is too similar to "' . $result['Level']['name'] . '" by ' . $result['User']['username'] . '. Please be more creative.');
                return false;
            }

            $this->set('level_filename', $levelFilename);
            $this->data['Level']['content'] = preg_replace("/\r?\n?LevelDatabaseId[^\n]*/", "", $this->data['Level']['content']);
        }

        foreach (array('levelgen', 'content', 'name', 'description') as $field) {
            if(isset($this->data['Level'][$field])) {
                $this->data['Level'][$field] = trim($this->data['Level'][$field]);
                $this->data['Level'][$field] = preg_replace("/\r?\n/", "\r\n", $this->data['Level'][$field]);
            }
        }
        return true;
    }

    public function beforeSave($options = array()) {
        if(!empty($this->data['Level']['content'])) {
            $teams = preg_grep('/^\s*Team/', split("\n", $this->data['Level']['content']));
            $this->data['Level']['team_count'] = count($teams);
        }

        if(isset($this->data['Level']['content']) || isset($this->data['Level']['levelgen'])) {
            $this->data['Level']['last_updated'] = date('Y:m:d h:i:s');
        } else {
            unset($this->data['Level']['last_updated']);
        }
    }

    public function afterSave($created, $options = array()) {
        $this->touch($this->id);
    }

    public function afterFind($results, $primary = false) {
        parent::afterFind($results, $primary);
        foreach($results as $k => $result) {
            if(isset($result['Level']['content'])) {
                $results[$k]['Level']['content'] .= "\r\nLevelDatabaseId " . $result['Level']['id'];
            }
        }
        return $results;
    }

    public function rate($user_id, $value) {
        $Rating = ClassRegistry::init('Rating');

        if(!$this->id) {
            array_push($this->validationErrors, 'You must specify a Level');
            return false;
        }

        if(count($this->findById($this->id)) < 1) {
            array_push($this->validationErrors, 'Specified level not found');
            return false;
        }

        $RATINGS = array(
            "1"       => 1,
            "up"      => 1,
            "good"    => 1,
            "0"       => 0,
            "neutral" => 0,
            "ok"      => 0,
            "-1"      => -1,
            "down"    => -1,
            "bad"     => -1
            );

        $value = $RATINGS[$value];

        $this->read('user_id');
        
        $rating = $Rating->findByUserIdAndLevelId($user_id, $this->id);

        if(empty($rating)) {
            // create a new rating
            $Rating->create();
            $Rating->set(array(
                    'level_id' => $this->id,
                    'user_id' => $user_id,
                    'value' => $value
            ));
        } else {
            // change the current rating
            $Rating->set(array(
                    'id' => $rating["Rating"]['id'],
                    'value' => $value
            ));
        }

        if(!$Rating->save()) {
            return false;
        }

        // recalculate rating
        $ratings = $Rating->findAllByLevelId($this->id);
        $total = 0;
        foreach ($ratings as $rating) {
            $total += intval($rating['Rating']['value']);
        }
        $this->saveField('rating', $total);
        return true;
    }

    public function findByTags($data = array()) {
        $this->LevelsTag->Behaviors->attach('Containable', array('autoFields' => false));
        $this->LevelsTag->Behaviors->attach('Search.Searchable');
        $query = $this->LevelsTag->getQuery('all', array(
                'conditions' => array('LevelsTag.tag_id' => $data['tags']),
                'fields' => array('level_id'),
                'contain' => array('Tag')
        ));
        return $query;
    }
}
?>
