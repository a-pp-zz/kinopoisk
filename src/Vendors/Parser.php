<?php
namespace AppZz\Http\Kinopoisk\Vendors;
use \AppZz\Http\Kinopoisk\Kinopoisk;
use \AppZz\Helpers\Arr;
use \AppZz\Helpers\HtmlDomParser;
use \AppZz\Http\Kinopoisk\Helpers\FastImage;

/**
 * Mobile version parser
 * @package Kinopoisk/Parser
 * @author CoolSwitcher
 * @version 3.0.0
 */
class Parser extends Kinopoisk {

	protected $_url_tpl = 'https://www.kinopoisk.ru/film/%d/';
	protected $_agent   = 'Mozilla/5.0 (iPhone; CPU iPhone OS 10_0 like Mac OS X) AppleWebKit/602.1.38 (KHTML, like Gecko) Version/10.0 Mobile/14A5297c Safari/602.1';

    public function __construct ($kpid = null)
    {
    	parent::__construct ($kpid);
    	$this->_set_url ();
    }

	public function parse ($force = false)
	{
		if ($this->_output AND is_file ($this->_output) AND $force !== true) {
			$body = file_get_contents($this->_output);
		} else {
			$body = $this->_get_url ();
			if ($this->_output) {
				file_put_contents($this->_output, $body);
			}
		}

		$data = $this->_parse ($body);

		if ( ! empty ($data)) {
			$check_str = '';

			foreach ($data as $value) {
				if ( ! is_array($value)) {
					$check_str .= $value;
				}
			}

			if (empty ($check_str)) {
				$data = NULL;
			} else {
				$data['kp_id'] = $this->_kpid;
			}
		}

		return $data;
	}

	private function _parse ($body)
	{
		$dom = HtmlDomParser::str_get_html($body);
		$ret = array ();

		$titles = array (
			'name'        => 'h1.movie-header__title',
			'original'    => 'h2.movie-header__original-title',
			'description' => 'p.descr',
			'year'        => '.movie-header__years',
			'genre'       => '.movie-header__genres',
			'country'     => '.movie-header__production',
			'rating_kp'   => '.details-table .movie-rating__value',
			'rating_imdb' => '.details-table .details-table__cell_even',
		);

		foreach ($titles as $key=>$pat) {
			$f = $dom->find($pat, 0);

			if ($f) {
				$ret[$key] = $f->plaintext;
			}

			unset ($f);
		}

		$f = $dom->find('.movie-page__description div');

		foreach ($f as $d) {
			if (isset ($d->itemprop) AND ($d->itemprop == 'description')) {
				$ret['description'] = $d->content;
				break;
			}
		}

		unset ($f);

		$persons = array (
			'actors'   => '.person-snippet__name',
			//'director' => '.movie-page__creators-name',
		);

		foreach ($persons as $key=>$pat) {
			$f = $dom->find($pat);

			foreach ($f as $item) {
				if ( ! empty ($item->itemprop) AND in_array ($item->itemprop, array_keys($persons))) {
					$ret[$item->itemprop][] = $item->plaintext;
				}
			}
		}

		unset ($f);

		$creators = array (
			'director', 'producer'
		);

		$f = $dom->find('.movie-page__qa-creators-container meta');

		foreach ($f as $item) {
			if ( ! empty ($item->itemprop) AND in_array ($item->itemprop, $creators)) {
				$ret[$item->itemprop][] = $item->content;
			}
		}

		unset ($f);

		$f = $dom->find('.movie-header__poster-wrap meta');

		foreach ($f as $item) {
			if ( ! empty ($item->itemprop) AND $item->itemprop == 'image') {
				$ret['poster'] = $item->content;
			}
		}

		unset ($f);

		$f = $dom->find('.photo-snippet__picture');

		foreach ($f as $img) {
			if ( !empty ($img->style))
				$ret['gallery'][] = $img->style;
		}

		if ( !empty ($ret['gallery']))
			$ret['gallery'] = array_slice ($ret['gallery'], 0, 5);

		unset ($f);

		$this->_populate($ret);

		return $ret;
	}

	private function _populate (&$ret)
	{
		foreach ($ret as $key=>&$value) {
			switch ($key) {
				case 'country':
					$values = explode (',', $value);
					$values = array_map ('trim', $values);
					$ret['duration'] = array_pop ($values);
					$value = implode (', ', $values);
				break;

				case 'director':
				case 'actors':
				case 'producer':
					$value = implode (', ', $value);
				break;

				case 'rating_kp':
				case 'rating_imdb':
					$value = (float)$value;
				break;	

				case 'gallery':
					$pat = '#('.Kinopoisk::YA_CDN_HOST.'\/images\/kadr\/sm_\d+\.jpg)#iu';

					foreach ($value as &$image) {

						if (preg_match($pat, $image, $parts)) {
							$image = 'https://' . $parts[1];
							$thumb = $image;
							$image = str_replace ('sm_', '', $image);
							$fi = new FastImage ($image);
							$size = $fi->getSize ();
							$width = Arr::get($size, 0, 0);
							$height = Arr::get($size, 1, 0);
							$image = array ('image'=>$image, 'thumb'=>$thumb, 'width'=>$width, 'height'=>$height);
						} else {
							continue;
						}
					}
				break;
			}
		}
	}
}