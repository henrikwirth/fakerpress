<?php

namespace FakerPress\Provider;

use FakerPress\Utils;
use Faker\Provider\Base;
use Faker\Provider\Lorem;
use Faker\Provider\Internet;

class HTML extends Base {
	/**
	 * @param \Faker\Generator $generator
	 */
	public function __construct( \Faker\Generator $generator ) {
		$this->generator = $generator;

		$provider = new Internet( $this->generator );
		$this->generator->addProvider( $provider );

		$provider = new Image\PlaceHoldIt( $this->generator );
		$this->generator->addProvider( $provider );

		$provider = new Image\LoremPicsum( $this->generator );
		$this->generator->addProvider( $provider );

		$provider = new Image\LoremPixel( $this->generator );
		$this->generator->addProvider( $provider );
	}

	static public $sets = [
		'self_close' => [ 'img', 'hr', '!--more--' ],
		'header'     => [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ],
		'list'       => [ 'ul', 'ol' ],
		'block'      => [ 'div', 'p', 'blockquote' ],
		'item'       => [ 'li' ],
		'inline'     => [
			'b',
			'big',
			'i',
			'small',
			'tt',
			'abbr',
			'cite',
			'code',
			'em',
			'strong',
			'a',
			'bdo',
			'br',
			'img',
			'q',
			'span',
			'sub',
			'sup',
			'hr',
		],
		'wp'         => [ '!--more--' ]
	];

	private function filter_html_comments( $element = '' ) {
		return ! preg_match( '/<?!--(.*?)-->?/i', $element );
	}

	private function has_element( $needle = '', $haystack = [] ) {
		$needle   = trim( $needle );
		$filtered = array_filter( $haystack, function ( $element ) use ( $needle ) {
			return preg_match( "/<?(!--)? ?({$needle})+ ?(--)?>?/i", $element ) !== 0;
		} );

		return count( $filtered ) > 0;
	}

	public function html_elements( $args = [] ) {
		$html = [];

		$defaults = [
			'qty'                 => [ 5, 25 ],
			'elements'            => array_merge( self::$sets['header'], self::$sets['list'], self::$sets['block'] ),
			'attr'                => [],
			'exclude'             => [ 'div' ],
			'allow_html_comments' => false,
		];

		$args                   = (object) wp_parse_args( $args, $defaults );
		$args->did_more_element = false;

		// Randomize the quantity based on range
		$args->qty = Utils::instance()->get_qty_from_range( $args->qty );

		$max_to_more = ( $args->qty / 2 ) + $this->generator->numberBetween( 0, max( floor( $args->qty / 2 ), 1 ) );
		$min_to_more = ( $args->qty / 2 ) - $this->generator->numberBetween( 0, max( floor( $args->qty / 2 ), 1 ) );

		for ( $i = 0; $i < $args->qty; $i ++ ) {
			$exclude = $args->exclude;
			if ( isset( $element ) ) {
				// Here we check if we need to exclude some elements from the next
				// This one is to exclude header elements from apearing one after the other, or in the end of the string
				if ( in_array( $element, self::$sets['header'] ) || $args->qty - 1 === $i ) {
					$exclude = array_merge( (array) $exclude, self::$sets['header'] );
				} elseif ( $i > 1 && ( in_array( $els[ $i - 1 ], self::$sets['list'] ) || in_array( $els[ $i - 2 ], self::$sets['list'] ) ) ) {
					$exclude = array_merge( (array) $exclude, self::$sets['list'] );
				}
			}

			$elements = array_diff( $args->elements, $exclude );

			if ( ! $args->allow_html_comments ) {
				$elements = array_filter( $elements, [ $this, 'filter_html_comments' ] );
			}

			$els[] = $element = Base::randomElement( $elements );

			$html[] = $this->element( $element, $args->attr, null, $args );

			if (
				$this->generator->numberBetween( 0, 100 ) <= 80
				&& ! $args->did_more_element
				&& $args->qty > 2
				&& $this->has_element( '!--more--', $args->elements )
				&& $i < $max_to_more
				&& $i > $min_to_more
			) {
				$html[]                 = $this->element( '!--more--' );
				$args->did_more_element = true;
			}
		}

		return (array) $html;
	}

	private function html_element_img( $element, $wrapper, $sources = [ 'placeholdit', 'lorempicsum' ] ) {
		if ( ! isset( $wrapper->attr['class'] ) ) {
			$wrapper->attr['class'][] = $this->generator->optional( 40, null )->randomElement( [
				'aligncenter',
				'alignleft',
				'alignright'
			] );
			$wrapper->attr['class'][] = "wp-block-image";
			$wrapper->attr['class'][] = "size-large";

			$wrapper->attr['class'] = array_filter( $wrapper->attr['class'] );
			$wrapper->attr['class'] = implode( ' ', $wrapper->attr['class'] );
		}

		if ( ! isset( $element->attr['alt'] ) ) {
			$element->attr['alt'] = rtrim( $this->generator->optional( 70, null )->sentence( Base::randomDigitNotNull() ), '.' );
		}

		$image_id = $this->get_img_src( $sources );

		if ( ! isset( $element->attr['src'] ) ) {
			$element->attr['src'] = wp_get_attachment_url( $image_id );
		}

		$element->attr = array_filter( $element->attr );

		return (object) [
			'element'  => $element,
			'wrapper'  => $wrapper,
			'image_id' => $image_id,
		];
	}

	public function get_img_src( $sources = [ 'placeholdit', 'lorempicsum' ] ) {
		$images       = \FakerPress\Module\Post::fetch( [ 'post_type' => 'attachment' ] );
		$image        = false;
		$count_images = count( $images );
		$optional     = ( $count_images * 2 );
		$optional     = $optional > 100 ? 100 : $optional;

		if ( $count_images > 0 ) {
			$image = $this->generator->optional( $optional, $image )->randomElement( $images );
		}

		if ( false === $image ) {
			$image = \FakerPress\Module\Attachment::instance()
			                                      ->set( 'attachment_url', $this->generator->randomElement( $sources ) )
			                                      ->generate()->save();
		}

		return $image;
	}

	public function random_apply_element( $element = 'a', $max = 5, $text = null ) {
		$words       = explode( ' ', $text );
		$total_words = count( $words );
		$sentences   = [];

		for ( $i = 0; $i < $total_words; $i ++ ) {
			$group    = Base::numberBetween( 1, Base::numberBetween( 3, 9 ) );
			$sentence = [];

			for ( $k = 0; $k < $group; $k ++ ) {
				$index = $i + $k;

				if ( ! isset( $words[ $index ] ) ) {
					break;
				}

				$sentence[] = $words[ $index ];

			}
			$i += $k;

			$sentences[] = implode( ' ', $sentence );
		}

		$qty = $max - Base::numberBetween( 0, $max );

		if ( 0 === $qty ) {
			return $text;
		}

		$indexes = floor( count( $sentences ) / $qty );

		for ( $i = 0; $i < $qty; $i ++ ) {
			$index = ( $indexes * $i ) + Base::numberBetween( 0, $indexes );

			if ( isset( $sentences[ $index ] ) ) {
				$sentences[ $index ] = $this->element( $element, [], $sentences[ $index ] );
			}
		}

		return implode( ' ', $sentences );
	}

	public function get_elements_gutenberg_fields( $name ) {
		$tag       = null;
		$options   = null;
		$extra_tag = null;


		if ( 'p' === $name ) {
			$tag = 'paragraph';
		} elseif ( 'ul' === $name ) {
			$tag = 'list';
		} elseif ( 'ol' === $name ) {
			$tag     = 'list';
			$options = (object) [
				"ordered" => "true"
			];
		} elseif ( in_array( $name, self::$sets['header'] ) ) {
			$tag     = 'heading';
			$options = (object) [
				'level' => $name[1],
			];
		} elseif ( '!--more--' === $name ) {
			$tag = 'more';
		} elseif ( 'blockquote' === $name ) {
			$tag       = 'quote';
			$extra_tag = 'p';
		} elseif ( 'hr' === $name ) {
			$tag = 'separator';
		} elseif ( 'img' === $name ) {
			$tag     = 'image';
			$options = (object) [
				'sizeSlug' => 'large',
			];
		}


		return (object) [
			'tag'       => $tag,
			'options'   => $options,
			'extra_tag' => $extra_tag,
		];
	}

	public function element( $name = 'div', $attr = [], $text = null, $args = null ) {
		$element = (object) [
			'name' => $name,
			'attr' => $attr,
		];

		$wrapper = (object) [
			'attr' => [],
		];

		if ( empty( $element->name ) ) {
			return false;
		}


		$gutenberg_fields = self::get_elements_gutenberg_fields( $name );

		$options = [];


		$element->one_liner = in_array( $element->name, self::$sets['self_close'] );

		$html = [];

		if ( 'a' === $element->name ) {
			if ( ! isset( $element->attr['title'] ) ) {
				$element->attr['title'] = Lorem::sentence( Base::numberBetween( 1, Base::numberBetween( 3, 9 ) ) );
			}
			if ( ! isset( $element->attr['href'] ) ) {
				$element->attr['href'] = $this->generator->url();
			}
		}


		if ( 'img' === $element->name ) {
			$sources = [ 'placeholdit', 'lorempicsum' ];
			if ( is_object( $args ) && $args->sources ) {
				$sources = $args->sources;
			}

			$wrapper->name = 'figure';

			$image_obj = $this->html_element_img( $element, $wrapper, $sources );

			$element  = $image_obj->element;
			$wrapper  = $image_obj->wrapper;

			$gutenberg_fields->options->id = $image_obj->image_id;
		}


		if ( 'hr' === $element->name ) {
			$element->attr['class'] = 'wp-block-separator';
		} else if ( 'blockquote' === $element->name ) {
			$element->attr['class'] = 'wp-block-quote';
		}

		if ( ! empty( $gutenberg_fields->options ) ) {
			foreach ( $gutenberg_fields->options as $key => $value ) {
				$options[] = sprintf( '"%s": %s', $key, esc_attr( $value ) );
			}
		}

		$attributes = [];
		foreach ( $element->attr as $key => $value ) {
			$attributes[] = sprintf( '%s="%s"', $key, esc_attr( $value ) );
		}

		$wrapper_attributes = [];
		foreach ( $wrapper->attr as $key => $value ) {
			$wrapper_attributes[] = sprintf( '%s="%s"', $key, esc_attr( $value ) );
		}


//		$html[] = sprintf( '<%s%s>', $element->name, ( ! empty( $attributes ) ? ' ' : '' ) . implode( ' ', $attributes ) );
		//( ! empty( $attributes ) ? ' ' : '' ) . implode( ' ', $attributes )
		//TODO: apply attributes

		// Gutenberg Block Wrapper like: <!-- wp:list -->
		if ( ! empty( $gutenberg_fields->tag ) ) {

			$html[] = sprintf( '<!-- wp:%s -->', empty( $gutenberg_fields->options ) ? $gutenberg_fields->tag : ( $gutenberg_fields->tag . " {" . implode( ", ", $options ) . " }" ) );
		}

		// Wrapper like: <figure><img>
		if ( ! empty( $wrapper->name ) ) {
			$html[] = sprintf( '<%s%s>', $wrapper->name, ( ! empty( $wrapper_attributes ) ? ' ' : '' ) . implode( ' ', $wrapper_attributes ) );
		}

		// Normal HTML-Tag
		$html[] = sprintf( '<%s%s>', $element->name, ( ! empty( $attributes ) ? ' ' : '' ) . implode( ' ', $attributes ) );

		// If there is an extra inner tag needed like the p in: <blockquote><p></p></blockquote>
		if ( ! empty( $gutenberg_fields->extra_tag ) ) {
			$html[] = sprintf( '<%s>', $gutenberg_fields->extra_tag );
		}

		if ( ! $element->one_liner ) {
			if ( ! is_null( $text ) ) {
				$html[] = $text;
			} elseif ( in_array( $element->name, self::$sets['inline'] ) ) {
				$text   = Lorem::text( Base::numberBetween( 5, 25 ) );
				$html[] = substr( $text, 0, strlen( $text ) - 1 );
			} elseif ( in_array( $element->name, self::$sets['item'] ) ) {
				$text   = Lorem::text( Base::numberBetween( 10, 60 ) );
				$html[] = substr( $text, 0, strlen( $text ) - 1 );
			} elseif ( in_array( $element->name, self::$sets['list'] ) ) {
				for ( $i = 0; $i < Base::numberBetween( 1, 15 ); $i ++ ) {
					$html[] = $this->element( 'li' );
				}
			} elseif ( in_array( $element->name, self::$sets['header'] ) ) {
				$text   = Lorem::text( Base::numberBetween( 60, 200 ) );
				$html[] = substr( $text, 0, strlen( $text ) - 1 );
			} else {
				$html[] = $this->random_apply_element( 'a', Base::numberBetween( 0, 10 ), Lorem::paragraph( Base::numberBetween( 2, 40 ) ) );
			}

			if ( ! empty( $gutenberg_fields->extra_tag ) ) {
				$html[] = sprintf( '</%s>', $gutenberg_fields->extra_tag );
			}

			$html[] = sprintf( '</%s>', $element->name );
		}

		if ( ! empty( $wrapper->name ) ) {
			$html[] = sprintf( '</%s>', $wrapper->name );
		}

		if ( ! empty( $gutenberg_fields->tag ) ) {
			$html[] = sprintf( '<!-- /wp:%s -->', $gutenberg_fields->tag );
		}

		return implode( '', $html );
	}

}
