<?php
/* vim:set softtabstop=4 shiftwidth=4 expandtab: */
/**
 *
 * LICENSE: GNU General Public License, version 2 (GPLv2)
 * Copyright 2001 - 2014 Ampache.org
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License v2
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 */

class TVShow extends database_object implements library_item
{
    /* Variables from DB */
    public $id;
    public $name;
    public $summary;
    public $year;

    public $tags;
    public $f_tags;
    public $episodes;
    public $seasons;
    public $f_name;
    public $link;
    public $f_link;


    // Constructed vars
    private static $_mapcache = array();

    /**
     * TV Show
     * Takes the ID of the tv show and pulls the info from the db
     */
    public function __construct($id='')
    {
        /* If they failed to pass in an id, just run for it */
        if (!$id) { return false; }

        /* Get the information from the db */
        $info = $this->get_info($id);

        foreach ($info as $key=>$value) {
            $this->$key = $value;
        } // foreach info

        return true;

    } //constructor

    /**
     * gc
     *
     * This cleans out unused tv shows
     */
    public static function gc()
    {
        $sql = "DELETE FROM `tvshow` USING `tvshow` LEFT JOIN `tvshow_season` ON `tvshow_season`.`tvshow` = `tvshow`.`id` " .
            "WHERE `tvshow_season`.`id` IS NULL";
        Dba::write($sql);
    }

    /**
     * get_from_name
     * This gets a tv show object based on the tv show name
     */
    public static function get_from_name($name)
    {
        $sql = "SELECT `id` FROM `tvshow` WHERE `name` = ?'";
        $db_results = Dba::read($sql, array($name));

        $row = Dba::fetch_assoc($db_results);

        $object = new TVShow($row['id']);
        return $object;

    } // get_from_name

    /**
     * get_seasons
     * gets the tv show seasons
     * of
     */
    public function get_seasons()
    {
        $sql = "SELECT `id` FROM `tvshow_season` WHERE `tvshow` = ? ORDER BY `season_number`";
        $db_results = Dba::read($sql, array($this->id));
        $results = array();
        while ($r = Dba::fetch_assoc($db_results)) {
            $results[] = $r['id'];
        }

        return $results;

    } // get_seasons

    /**
     * get_songs
     * gets all episodes for this tv show
     */
    public function get_episodes()
    {
        $sql = "SELECT `tvshow_episode`.`id` FROM `tvshow_episode` ";
        if (AmpConfig::get('catalog_disable')) {
            $sql .= "LEFT JOIN `video` ON `video`.`id` = `tvshow_episode`.`id` ";
            $sql .= "LEFT JOIN `catalog` ON `catalog`.`id` = `video`.`catalog` ";
        }
        $sql .= "LEFT JOIN `tvshow_season` ON `tvshow_season`.`tvshow` = `tvshow_episode`.`season` ";
        $sql .= "WHERE `tvshow_season`.`tvshow`='" . Dba::escape($this->id) . "' ";
        if (AmpConfig::get('catalog_disable')) {
            $sql .= "AND `catalog`.`enabled` = '1' ";
        }
        $sql .= "ORDER BY `tvshow_season`.`season_number`, `tvshow_episode`.`episode_number`";
        $db_results = Dba::read($sql);

        $results = array();
        while ($r = Dba::fetch_assoc($db_results)) {
            $results[] = $r['id'];
        }

        return $results;

    } // get_episodes

    /**
     * _get_extra info
     * This returns the extra information for the tv show, this means totals etc
     */
    private function _get_extra_info()
    {
        // Try to find it in the cache and save ourselves the trouble
        if (parent::is_cached('tvshow_extra', $this->id) ) {
            $row = parent::get_from_cache('tvshow_extra', $this->id);
        } else {
            $sql = "SELECT COUNT(`tvshow_episode`.`id`) AS `episode_count` FROM `tvshow_season` " .
                "LEFT JOIN `tvshow_episode` ON `tvshow_episode`.`season` = `tvshow_season`.`id` " .
                "WHERE `tvshow_season`.`tvshow` = ?";
            $db_results = Dba::read($sql, array($this->id));
            $row = Dba::fetch_assoc($db_results);

            $sql = "SELECT COUNT(`tvshow_season`.`id`) AS `season_count` FROM `tvshow_season` " .
                "WHERE `tvshow_season`.`tvshow` = ?";
            $db_results = Dba::read($sql, array($this->id));
            $row2 = Dba::fetch_assoc($db_results);
            $row['season_count'] = $row2['season_count'];

            parent::add_to_cache('tvshow_extra',$this->id,$row);
        }

        /* Set Object Vars */
        $this->episodes = $row['episode_count'];
        $this->seasons = $row['season_count'];

        return $row;

    } // _get_extra_info

    /**
     * format
     * this function takes the object and reformats some values
     */
    public function format()
    {
        $this->f_name = $this->name;
        $this->link = AmpConfig::get('web_path') . '/tvshows.php?action=show&tvshow=' . $this->id;
        $this->f_link = '<a href="' . $this->link . '" title="' . $this->f_name . '">' . $this->f_name . '</a>';

        $this->_get_extra_info();
        $this->tags = Tag::get_top_tags('tvshow', $this->id);
        $this->f_tags = Tag::get_display($this->tags, true, 'tvshow');

        return true;
    }

    public function get_keywords()
    {
        $keywords = array();
        $keywords['tvshow'] = array('important' => true,
            'label' => T_('TV Show'),
            'value' => $this->f_name);
        $keywords['type'] = array('important' => false,
            'label' => null,
            'value' => 'tvshow'
        );

        return $keywords;
    }

    public function get_fullname()
    {
        return $this->f_name;
    }

    public function get_parent()
    {
        return null;
    }

    public function get_childrens()
    {
        return array('tvshow_season' => $this->get_seasons());
    }

    public function get_medias($filter_type = null)
    {
        $medias = array();
        if (!$filter_type || $filter_type == 'video') {
            $episodes = $this->get_episodes();
            foreach ($episodes as $episode_id) {
                $medias[] = array(
                    'object_type' => 'video',
                    'object_id' => $episode_id
                );
            }
        }
        return $medias;
    }

    public function get_user_owner()
    {
        return null;
    }

    public function get_default_art_kind()
    {
        return 'default';
    }

    /**
     * check
     *
     * Checks for an existing tv show; if none exists, insert one.
     */
    public static function check($name, $year, $readonly = false)
    {
        // null because we don't have any unique id like mbid for now
        if (isset(self::$_mapcache[$name]['null'])) {
            return self::$_mapcache[$name]['null'];
        }

        $id = 0;
        $exists = false;

        if (!$exists) {
            $sql = 'SELECT `id` FROM `tvshow` WHERE `name` LIKE ? AND `year` = ?';
            $db_results = Dba::read($sql, array($name, $year));

            $id_array = array();
            while ($row = Dba::fetch_assoc($db_results)) {
                $key = 'null';
                $id_array[$key] = $row['id'];
            }

            if (count($id_array)) {
                $id = array_shift($id_array);
                $exists = true;
            }
        }

        if ($exists) {
            self::$_mapcache[$name]['null'] = $id;
            return $id;
        }

        if ($readonly) {
            return null;
        }

        $sql = 'INSERT INTO `tvshow` (`name`, `year`) ' .
            'VALUES(?, ?)';

        $db_results = Dba::write($sql, array($name, $year));
        if (!$db_results) {
            return null;
        }
        $id = Dba::insert_id();

        self::$_mapcache[$name]['null'] = $id;
        return $id;

    }

    /**
     * update
     * This takes a key'd array of data and updates the current tv show
     */
    public function update($data)
    {
        // Save our current ID
        $current_id = $this->id;

        // Check if name is different than current name
        if ($this->name != $data['name'] || $this->year != $data['year']) {
            $tvshow_id = self::check($data['name'], $data['year'], true);

            // If it's changed we need to update
            if ($tvshow_id != $this->id && $tvshow_id != null) {
                $seasons = $this->get_seasons();
                foreach ($seasons as $season_id) {
                    Season::update_tvshow($tvshow_id, $season_id);
                }
                $current_id = $tvshow_id;
                self::gc();
            } // end if it changed
        }

        $sql = 'UPDATE `tvshow` SET `name` = ?, `year` = ?, `summary` = ? WHERE `id` = ?';
        Dba::write($sql, array($data['name'], $data['year'], $data['summary'], $current_id));

        $override_childs = false;
        if ($data['apply_childs'] == 'checked') {
            $override_childs = true;
        }
        $this->update_tags($data['edit_tags'], $override_childs, $current_id);

        return $current_id;

    } // update

    /**
     * update_tags
     *
     * Update tags of tv shows
     */
    public function update_tags($tags_comma, $override_childs, $current_id = null)
    {
        if ($current_id == null) {
            $current_id = $this->id;
        }

        Tag::update_tag_list($tags_comma, 'tvshow', $current_id);

        if ($override_childs) {
            $episodes = $this->get_episodes();
            foreach ($episodes as $ep_id) {
                Tag::update_tag_list($tags_comma, 'episode', $ep_id);
            }
        }
    }

} // end of tvshow class