<?php
namespace AppZz\Http\Kinopoisk\Vendors;
use \AppZz\Http\Kinopoisk\Kinopoisk;
use \AppZz\Helpers\Arr;
use \AppZz\Helpers\HtmlDomParser;
use \AppZz\Http\Helpers\FastImage;

/**
 * Mobile version parser
 * @package Kinopoisk/Parser
 * @author CoolSwitcher
 * @version 5.0.0
 */
class Parser extends Kinopoisk {

	protected $_url_tpl = 'https://www.kinopoisk.ru/film/%d/';
	protected $_agent   = 'Mozilla/5.0 (iPhone; CPU iPhone OS 10_0 like Mac OS X) AppleWebKit/602.1.38 (KHTML, like Gecko) Version/10.0 Mobile/14A5297c Safari/602.1';

	protected $_fields = array (
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

	protected $_titles = false;
	protected $_result;

    public function __construct ($kpid = null)
    {
    	parent::__construct ($kpid);
    	$this->_set_url ();
    }

    public function destruct ()
    {
    	$this->_result = null;
    }

    public function fields (array $fields = array ())
    {
    	$this->_fields = $fields;
    	return $this;
    }

    public function titles ($titles = true)
    {
    	$this->_titles = (bool)$titles;
    	return $this;
    }

	public function parse ()
	{
		if ($this->_output AND is_file ($this->_output) AND $this->_force !== true) {
			$body = file_get_contents($this->_output);
		} else {
			$body = $this->_get_url ();
			if ($this->_output) {
				file_put_contents($this->_output, $body);
			}
		}

		$this->_parse ($body);
		$this->_populate();

		return $this->_result;
	}

	private function _clean_string ($str)
	{
		$str = trim ($str);
		$str = preg_replace("#\s{1,}$#iu", "", $str);
		return $str;
	}

	private function _parse ($body)
	{
		$dom = HtmlDomParser::str_get_html($body);
		$this->_result = array ();

		$titles = array (
			'name'        => 'h1.movie-header__title',
			'original'    => 'h2.movie-header__original-title',
			//'description' => 'p.descr',
			'year'        => '.movie-header__years',
			'genre'       => '.movie-header__genres',
			'country'     => '.movie-header__production',
			'rating_kp'   => '.details-table .movie-rating__value',
			'rating_imdb' => '.details-table .details-table__cell_even',
		);

		foreach ($titles as $key=>$pat) {

			if ( ! array_key_exists($key, $this->_fields)) {
				continue;
			}

			$f = $dom->find($pat, 0);

			if ($f) {
				$this->_result[$key] = $this->_clean_string ($f->plaintext);
			}

			unset ($f);
		}

		$f = $dom->find('.movie-page__description div');

		foreach ($f as $d) {
			if ( ! array_key_exists('description', $this->_fields)) {
				continue;
			}

			if (isset ($d->itemprop) AND ($d->itemprop == 'description')) {
				$this->_result['description'] = $this->_clean_string ($d->content);
				break;
			}
		}

		unset ($f);

		$persons = array (
			'actors'   => '.person-snippet__name',
		);

		foreach ($persons as $key=>$pat) {
			if ( ! array_key_exists('actors', $this->_fields)) {
				continue;
			}

			$f = $dom->find($pat);

			foreach ($f as $item) {
				if ( ! empty ($item->itemprop) AND in_array ($item->itemprop, array_keys($persons))) {
					$this->_result[$item->itemprop][] = $this->_clean_string ($item->plaintext);
				}
			}
		}

		unset ($f);

		$creators = array (
			'director', 'producer'
		);

		$f = $dom->find('.movie-page__qa-creators-container meta');

		foreach ($f as $item) {
			$creator = $item->itemprop;

			if ( ! array_key_exists($creator, $this->_fields)) {
				continue;
			}
			if ( ! empty ($creator) AND ! empty ($item->content)) {
				$this->_result[$item->itemprop][] = $this->_clean_string ($item->content);
			}
		}

		unset ($f);

		if (array_key_exists('poster', $this->_fields)) {
			$f = $dom->find('.movie-header__poster-wrap meta');

			foreach ($f as $item) {
				if (! empty ($item->itemprop) AND $item->itemprop == 'image') {
					$this->_result['poster'] = $this->_clean_string ($item->content);
				}
			}

			unset ($f);
		}

		if (array_key_exists('picshots', $this->_fields)) {
			$f = $dom->find('.photo-snippet__picture');

			foreach ($f as $img) {
				if ( ! empty ($img->style)) {
					$this->_result['picshots'][] = $this->_clean_string ($img->style);
				}
			}

			unset ($f);
		}

		return $this;
	}

	private function _populate ()
	{
		if ( ! empty ($this->_result)) {
			$check_str = '';

			foreach ($this->_result as $value) {
				if ( ! is_array($value)) {
					$check_str .= $value;
				}
			}

			if (empty ($check_str)) {
				$this->_result = false;
				return $this;
			}
		} else {
			$this->_result = false;
			return $this;
		}

		if (array_key_exists('kp_id', $this->_fields)) {
			$this->_result['kp_id'] = $this->_kpid;
		}

		foreach ($this->_result as $key=>&$value) {
			switch ($key) {
				case 'country':
					$values = explode (',', $value);

					if ( ! empty ($values) AND count ($values) > 1) {
						$values = array_map ('trim', $values);
						$duration = array_pop ($values);

						if (array_key_exists('duration', $this->_fields)) {
							$this->_result['duration'] = $duration;
						}

						$value = implode (', ', $values);
					}
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

				case 'poster':
					if ($this->_check_poster ($value)) {
						$fi = new FastImage ($value);
						$size = $fi->get_size ();

						if (is_array($size) AND count ($size) === 2) {
							list ($width, $height) = $size;
						} else {
							$width = $height = 0;
						}

						$value = array ('image'=>$value, 'width'=>$width, 'height'=>$height);
						unset($fi);
					} else {
						unset ($this->_result['poster']);
					}
				break;

				case 'picshots':
					$pat = '#('.Kinopoisk::YA_CDN_HOST.'\/images\/kadr\/([\w\-_]+)\.(jpg|jpeg|png|gif))#iu';
					foreach ($value as &$image) {

						if (preg_match($pat, $image, $parts)) {
							$image = 'https://' . $parts[1];
							$thumb = $image;
							$image = str_replace ('sm_', '', $image);
							$fi = new FastImage ($image);
							$size = $fi->get_size ();

							if (is_array($size) AND count ($size) === 2) {
								list ($width, $height) = $size;
							} else {
								$width = $height = 0;
							}

							unset($fi);

							$image = array ('image'=>$image, 'thumb'=>$thumb, 'width'=>$width, 'height'=>$height);
						} else {
							continue;
						}
					}
				break;
			}

			if ($this->_titles) {
				$value = array ('field'=>$key, 'title'=>Arr::get($this->_fields, $key, ''), 'value'=>$value);
			}
		}

		if ($this->_titles) {
			$this->_result = array_values ($this->_result);
		}
	}
}
