<?php
namespace AppZz\Http\Kinopoisk;
use \AppZz\Helpers\Arr;
use \AppZz\Http\CurlClient;

/**
 * @package Kinopoisk
 * @author CoolSwitcher
 * @version 3.0.0
 */
class Kinopoisk {

    const YA_CDN_HOST = 'st.kp.yandex.net';

    protected $_timeout = 15;
    protected $_proxy   = false;
    protected $_kpid;
    protected $_url_tpl;
    protected $_url;
    protected $_output;
    protected $_force   = false;

    public function __construct ($kpid = null)
    {
        $kpid = intval ($kpid);

        if ( ! empty ($kpid)) {
            $this->_kpid = $kpid;
        }
    }

    public static function factory ($vendor, $kpid = null)
    {
        $vendor = '\AppZz\Http\Kinopoisk\Vendors\\' . $vendor;
        return new $vendor($kpid);
    }

    public function parser ()
    {
        return Kinopoisk::factory('Parser', $this->_kpid);
    }

    public function rating ()
    {
        return Kinopoisk::factory('Rating', $this->_kpid);
    }

    public function timeout ($timeout)
    {
        $this->_timeout = intval ($timeout);
        return $this;
    }

    public function agent ($agent)
    {
        $this->_agent = $agent;
        return $this;
    }

    public function proxy ($proxy)
    {
        $this->_proxy = $proxy;
        return $this;
    }

    public function output ($output)
    {
        $this->_output = $output;
        return $this;
    }

    public function force ($force = true)
    {
        $this->_force = $force;
        return $this;
    }

    protected function _set_url ()
    {
        if ($this->_kpid) {
            $this->_url = sprintf ($this->_url_tpl, $this->_kpid);
        }

        return $this;
    }

    protected function _get_url ($url = NULL)
    {
        if (empty($url)) {
            $url = $this->_url;
        }

        $request = CurlClient::get($url, array ('force-version'=>'touch'));
        $request->agent($this->_agent);
        $request->referer('https://www.kinopoisk.ru/');

        if ($this->_proxy) {
            $proxy_host = Arr::get ($this->_proxy, 'host');
            unset ($this->_proxy['host']);
            $request->proxy ($proxy_host, $this->_proxy);
        }

        $request->accept('html', 'gzip', 'ru-RU');

        $cookie_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . sprintf ('kinopoisk-%s.ru.txt', uniqid(true));
        $request->cookie_file($cookie_file);
        $response = $request->send();

        if ($response !== 200) {
            $this->_error ('Ошибка получения данных c КП', $response);
        }

        $body = $request->get_body();
        $headers = $request->get_headers();

        if (empty($body)) {
            $this->_error ('Пустой исходный код страницы', 1001);
        }

        return $body;
    }

    protected function _error ($message, $code = 0)
    {
        throw new \Exception ($message, $code);
    }
}
