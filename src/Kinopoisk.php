<?php
namespace AppZz\Http\Kinopoisk;
use \AppZz\Helpers\Arr;

class Kinopoisk {

    const ST_URL       = 'https://st.kp.yandex.net/images/';

    protected $_timeout  = 15;
    protected $_agent    = 'KP Client 1.0';
    protected $_proxy;

    public static function factory ($vendor)
    {
        $vendor = '\AppZz\Http\TMDB\Vendors\\' . $vendor;
        return new $vendor();
    }

    public function timeout ($timeout)
    {
        $this->_timeout = intval ($timeout);
    }

    public function agent ($agent)
    {
        $this->_agent = $agent;
    }

    public function proxy ($proxy)
    {
        $this->_proxy = $proxy;
    }
}
