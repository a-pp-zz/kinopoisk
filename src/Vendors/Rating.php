<?php
/**
 * Get XML Rating
 * @package Kinopoisk/Rating
 * @author CoolSwitcher
 * @version 3.0.3
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

    public function get_data ($cache = false)
    {
        $this->_error ('Not implemnted', 0);
    }

    public function get_frames ($max = 0, $cache = false)
    {
        $this->_error ('Not implemnted', 0);
    }

    public function get_rating ($with_labels = false)
    {
        $url = sprintf (Rating::KP_URL_RATING, $this->_kpid);
        $this->_rating = (array)$this->_request ($url);
        return ! empty ($this->_rating);
    }

    protected function _populate ()
    {
        $this->_result['rating_kp'] = Arr::get($this->_rating, 'kp_rating', 0);
        $this->_result['rating_imdb'] = Arr::get($this->_rating, 'imdb_rating', 0);
        unset ($this->_rating);
        return $this;
    }
}
