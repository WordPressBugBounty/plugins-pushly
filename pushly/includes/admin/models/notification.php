<?php

class Notification implements JsonSerializable {
	public $id;
	public $template;
	public $delivery_spec;
	public $audience;
	public $meta;

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		$r = [
			'template'      => $this->template,
			'delivery_spec' => $this->delivery_spec,
			'audience'      => $this->audience,
			'meta'          => $this->meta,
		];

		if ( ! empty( $this->id ) ) {
			$r['id'] = $this->id;
		}

		return $r;
	}

	public static function from_post( $post, $pushly_meta ) {
		try {
			global $wp_version;
			$user = wp_get_current_user();

			$notification                = new Notification();
			$notification->audience      = new NotificationAudience();
			$notification->template      = new NotificationTemplate();
			$notification->delivery_spec = new NotificationDeliverySpec();
			$notification->meta          = array(
				'wordpress_version'    => $wp_version,
				'wordpress_post_id'    => $post['ID'],
				'wordpress_user_id'    => $user->ID,
				'wordpress_user_email' => $user->user_email,

			);

			// is this an existing notification that should be updated?
			if ( ! empty( $pushly_meta['existing_notification_id'] ) ) {
				$notification->id = intval( $pushly_meta['existing_notification_id'] );
			}

			// audience
			if ( empty( $pushly_meta['segment_ids'] ) ) {
				$notification->audience->all_subscribers = true;
			} else {
				$notification->audience->segment_ids = array_map( 'intval', $pushly_meta['segment_ids'] );
			}

			// template
			$notification->template->channels                   = new NotificationTemplateChannels();
			$notification->template->channels->web              = new NotificationTemplateChannelsWeb();
			$notification->template->channels->web->title       = sanitize_text_field( $post['title'] );
			$notification->template->channels->web->landing_url = $post['landing_url'];

			if ( ! empty( $post['body'] ) ) {
				$notification->template->channels->web->body = sanitize_text_field( $post['body'] );
			}

			if ( ! empty( $post['image_id'] ) ) {
				$image = wp_get_attachment_image_src( $post['image_id'], 'large' );
				if ( ! empty( $image ) ) {
					$notification->template->channels->web->image_url = $image[0];
				}
			}

			if ( ! empty( $post['tag_ids'] ) ) {
				function array_map_name( $value ) {
					return $value->name;
				}

				$tags                             = get_tags( array( 'include' => $post['tags'] ) );
				$tags                             = array_map( 'array_map_name', $tags );
				$notification->template->keywords = array_merge( $notification->template->keywords, $tags );
			} else if ( ! empty( $post['tag_names'] ) ) {
				$notification->template->keywords = array_unique( array_merge( $notification->template->keywords, $post['tag_names'] ) );
			}

			if ( ! empty( $post['categories'] ) ) {
				function array_map_cat_name( $value ) {
					return $value->cat_name;
				}

				$categories                       = get_categories( array( 'include' => $post['categories'] ) );
				$categories                       = array_map( 'array_map_cat_name', $categories );
				$notification->template->keywords = array_merge( $notification->template->keywords, $categories );
			} else if ( ! empty( $post['category_names'] ) ) {
				$notification->template->keywords = array_unique( array_merge( $notification->template->keywords, $post['category_names'] ) );
			}

			// delivery spec
			$notification->delivery_spec->window = 'STANDARD';
			$send_date                           = new DateTime( $post['schedule_date'], new DateTimeZone( 'utc' ) );
			$current_date                        = new DateTimeImmutable( 'now', new DateTimeZone( 'utc' ) );
			$notification->delivery_spec->type   = $send_date <= $current_date ? 'IMMEDIATE' : 'SCHEDULED';
			if ( $notification->delivery_spec->type === 'SCHEDULED' ) {
				$notification->delivery_spec->send_date_utc = $send_date->format( 'c' );
			}

			return $notification;
		} catch ( Exception $e ) {
			Pushly_Admin_Util::log_to_event_stream( "notification_build_error", $e->getMessage() );
			return;
		}
	}
}

class NotificationTemplate implements JsonSerializable {
	public $channels;
	public $keywords = array();

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'channels' => $this->channels,
			'keywords' => $this->keywords,
		];
	}
}

class NotificationTemplateChannels implements JsonSerializable {
	public $web;

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			// default is intentionally set to `this->web` to ensure all values are shown in the platform correctly
			// TODO: In a future version of this plugin we will enable multi-channel support
			'default' => $this->web,
			'web'     => $this->web,
		];
	}
}

class NotificationTemplateChannelsWeb implements JsonSerializable {
	public $title;
	public $body;
	public $landing_url;
	public $image_url;

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'title'       => $this->title,
			'body'        => $this->body,
			'landing_url' => $this->landing_url,
			'image_url'   => $this->image_url,
		];
	}
}

class NotificationDeliverySpec implements JsonSerializable {
	public $type;
	public $window;
	public $send_date_utc;

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		$r = [
			'type'   => $this->type,
			'window' => $this->window,
		];

		if ( $this->type === 'SCHEDULED' ) {
			$r['send_date_utc'] = $this->send_date_utc;
		}

		return $r;
	}
}

class NotificationAudience implements JsonSerializable {
	public $all_subscribers;
	public $segment_ids;

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		$r = [];
		if ( $this->all_subscribers ) {
			$r['all_subscribers'] = $this->all_subscribers;
		} else {
			$r['segment_ids'] = $this->segment_ids;
		}

		return $r;
	}
}