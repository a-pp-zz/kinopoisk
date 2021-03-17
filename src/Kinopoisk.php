<?php
namespace AppZz\Http\Kinopoisk;
use \AppZz\Helpers\Arr;
use \AppZz\Http\CurlClient;

/**
 * @package Kinopoisk
 * @author CoolSwitcher
 * @version 3.1.0
 */
abstract class Kinopoisk {

    /**
     * Kinopoisk ID
     * @var integer
     */
    protected $_kpid;

    /**
     * Curl timeout
     * @var integer
     */
    protected $_timeout = 15;

    /**
     * CurlClient proxy params
     * @var false|array
     */
    protected $_proxy   = false;

    /**
     * UserAgent
     * @var string
     */
    protected $_agent = 'Mozilla/5.0 (iPhone; CPU iPhone OS 10_0 like Mac OS X) AppleWebKit/602.1.38 (KHTML, like Gecko) Version/10.0 Mobile/14A5297c Safari/602.1';

    /**
     * Referer
     * @var string
     */
    protected $_referer = 'https://www.kinopoisk.ru/';

    /**
     * Contnent type html|json
     * @var string
     */
    protected $_content_type = 'html';

    /**
     * CurlClient Headers
     * @var array
     */
    protected $_headers = array ();

    /**
     * Force query avoiding cache
     * @var boolean
     */
    protected $_force   = false;

    /**
     * Labels
     * @var array
     */
    protected $_labels = array (
        'kp_id'       => 'ID КП',
        'name'        => 'Название',
        'original'    => 'Оригинальное название',
        'description' => 'Аннотация',
        'year'        => 'Год',
        'genre'       => 'Жанр',
        'country'     => 'Страна',
        'duration'    => 'Хронометраж',
        'rating_kp'   => 'Рейтинг КП',
        'rating_imdb' => 'Рейтинг IMDB',
        'actors'      => 'Актеры',
        'director'    => 'Режиссер',
        'producer'    => 'Продюсер',
        'poster'      => 'Постер',
        'picshots'    => 'Кадры'
    );

    /**
     * Populated result holder
     * @var mixed
     */
    protected $_result;

    /**
     * Temp film data holder
     * @var mixed
     */
    protected $_data = array ();

    /**
     * Temp film frames holder
     * @var mixed
     */
    protected $_frames = array ();

    /**
     * Temp rating data holder
     * @var mixed
     */
    protected $_rating = array ();

    /**
     * Errors holder
     * @var array
     */
    protected $_errors = array ();

    protected function __construct ($kpid = null)
    {
        $kpid = intval ($kpid);

        if ( ! empty ($kpid)) {
            $this->_kpid = $kpid;
            $this->_errors = array ();
        } else {
            $this->_error ('Wrong kpid', 0, true);
        }
    }

    public static function factory ($vendor, $kpid = null)
    {
        $vendor = '\AppZz\Http\Kinopoisk\Vendors\\' . $vendor;

        if ( ! class_exists($vendor)) {
            $this->_error ('Vendor '.$vendor.' not exists', 0, true);
        }

        return new $vendor ($kpid);
    }

    public static function parser ($kpid = null)
    {
        return Kinopoisk::factory('Parser', $kpid);
    }

    public static function rating ($kpid = null)
    {
        return Kinopoisk::factory('Rating', $kpid);
    }

    public static function api ($kpid = null)
    {
        return Kinopoisk::factory('Api', $kpid);
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

    public function force ($force = true)
    {
        $this->_force = $force;
        return $this;
    }

    public function labels (array $labels = array ())
    {
        $this->_labels = array_merge ($this->_labels, $labels);
        return $this;
    }

    public function get_errors ()
    {
        return ( ! empty ($this->_errors)) ? $this->_errors : false;
    }


    public function destruct ()
    {
        $this->_errors = array ();
        unset ($this->_result);
    }

    public function get_result ($with_labels = false)
    {
        $this->_populate();

        if ($with_labels) {
            $ret = array ();
            foreach ($this->_result as $key=>$value) {
                $ret[] = array ('field'=>$key, 'title'=>Arr::get($this->_labels, $key, ''), 'value'=>$value);
            }
            return $ret;
        }

        return $this->_result;
    }

    abstract public function get_data ($cache);
    abstract public function get_frames ($max = 5, $cache = false);
    abstract public function get_staff ($max = 10, $cache = false);
    abstract public function get_rating ();

    public static function duration_format ($duration, $after = array (), $show_seconds = FALSE)
    {
        $duration = intval ($duration);

        if ($duration < 60)
        {
            $show_seconds = TRUE;
        }

        if ($duration)
        {
            $hh = $mm = $ss = 0;
            $after = (array) $after;
            $after_hh = Arr::get ($after, 'hh', ':');
            $after_mm = Arr::get ($after, 'mm', ':');
            $after_ss = Arr::get ($after, 'ss', '');

            if ($duration >= 3600)
            {
                $hh = intval($duration / 3600);
                $duration -= ($hh * 3600);
            }

            if ($duration >= 60)
            {
                $mm = intval($duration / 60);
                $ss = $duration - ($mm * 60);
            }
            else
            {
                $mm = 0;
                $ss = $duration;
            }

            $fmt = '<hour><after_hour><min><after_min><sec><after_sec>';

            $srch = array ('<hour>', '<after_hour>', '<min>', '<after_min>', '<sec>', '<after_sec>');
            $repl = array ('', '', sprintf ('%02d', $mm), $after_mm, '', '');

            if ($show_seconds)
            {
                $repl[4] = sprintf ('%02d', $ss);
                $repl[5] = $after_ss;
            }
            elseif ($repl[3] == ':')
            {
                $repl[3] = '';
            }

            if ($hh OR $after_hh == ':')
            {
                $repl[0] = sprintf ('%01d', $hh);
                $repl[1] = $after_hh;
            }

            return str_replace ($srch, $repl, $fmt);
        }

        return FALSE;
    }

    public static function array_pluck ($array, $key)
    {
        $values = array ();

        foreach ($array as $row)
        {
            if (isset($row[$key]))
            {
                $values[] = $row[$key];
            }
        }

        return $values;
    }

    /**
     * Get image sizes from yandex cdn url
     * @param  string $url yandex cdn url
     * @return array
     */
    public static function cdn_image_size ($url)
    {
        $sizes = array (0, 0);

        if (preg_match('#\/(\d{3,4}x\d{3,4})$#iu', $url, $parts)) {
            $sizes = explode ('x', $parts[1]);
        }

        return $sizes;
    }

    /**
     * Make request via CurlClient
     * @param  string $url
     * @return mixed
     */
    protected function _request ($url = NULL)
    {
        $request = CurlClient::get($url);
        $request->agent($this->_agent);

        if ( ! empty ($this->_referer)) {
            $request->referer($this->_referer);
        }

        if ( ! empty ($this->_headers)) {
            $request->add_headers ($this->_headers);
        }

        if ($this->_proxy) {
            $proxy_host = Arr::get ($this->_proxy, 'host');
            unset ($this->_proxy['host']);
            $request->proxy ($proxy_host, $this->_proxy);
        }

        $request->accept($this->_content_type, 'gzip', 'ru-RU');

        $response = $request->send();

        if ($response !== 200) {
            $this->_error (sprintf('Ошибка получения данных по адресу: %s [%d]', $url, $response), $response, false);
            return false;
        }

        $body = $request->get_body();

        if (empty($body)) {
            $this->_error (sprintf('Был получен пустой ответ по адресу: %s', $url), 1001, false);
            return false;
        }

        return $body;
    }

    abstract protected function _populate ();

    protected function _get_rating ()
    {
        $rating = Kinopoisk::rating ($this->_kpid);
        $rating->get_rating();
        return $rating->get_result ();
    }

    protected function _error ($message, $code = 0, $throw = false)
    {
        if ($throw) {
            throw new \Exception ($message, $code);
        } else {
            $this->_errors[] = $message;
        }
    }
}
