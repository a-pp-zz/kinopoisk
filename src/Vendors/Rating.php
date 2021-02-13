<?php
/**
 * Get XML Rating
 * @package Kinopoisk/Rating
 * @author CoolSwitcher
 * @version 3.0.1
 */
namespace AppZz\Http\Kinopoisk\Vendors;
use AppZz\Helpers\Arr;
use AppZz\Http\Kinopoisk\Kinopoisk;

class Rating extends Kinopoisk {

    const KP_URL_RATING = 'https://rating.kinopoisk.ru/%d.xml';
    protected $_agent   = 'random';

    public function __construct ($kpid = null)
    {
        parent::__construct ($kpid);
    }

    public function get_result ()
    {
        $url = sprintf (Rating::KP_URL_RATING, $this->_kpid);
        $data = (array)$this->_request ($url);
        $ret = new \stdClass;
        $ret->kp = Arr::get($data, 'kp_rating', 0);
        $ret->imdb = Arr::get($data, 'imdb_rating', 0);
        return $ret;
    }
}
