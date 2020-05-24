<?php
/**
 * Get XML Rating
 * @package Kinopoisk/Rating
 * @author CoolSwitcher
 * @version 3.0.0
 */
namespace AppZz\Http\Kinopoisk\Vendors;
use AppZz\Helpers\Arr;
use AppZz\Http\Kinopoisk\Kinopoisk;

class Rating extends Kinopoisk {

    protected $_url_tpl = 'https://rating.kinopoisk.ru/%d.xml';
    protected $_agent   = 'random';

    public function __construct ($kpid = null)
    {
        parent::__construct ($kpid);
        $this->_set_url ();
    }

    public function get_rating ()
    {
        $data = (array)$this->_get_url ();
        $ret = new \stdClass;
        $ret->kp = Arr::get($data, 'kp_rating', 0);
        $ret->imdb = Arr::get($data, 'imdb_rating', 0);
        return $ret;
    }
}
