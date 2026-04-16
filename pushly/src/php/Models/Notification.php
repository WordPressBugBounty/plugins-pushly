<?php

namespace Pushly\Models;

use Pushly\Admin\Util;

class Notification implements \JsonSerializable {
	public ?int $id = null;
	public ?NotificationTemplate $template = null;
	public ?NotificationDeliverySpec $delivery_spec = null;
	public ?NotificationAudience $audience = null;
	public ?array $meta = null;

	#[\ReturnTypeWillChange]
	public function jsonSerialize(): array {
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

	/**
	 * Factory: builds a Notification from a post data array and pushly meta options.
	 *
	 * @param array $post         Flat array of post fields (ID, title, body, landing_url, etc.)
	 * @param array $pushly_meta  Pushly-specific options (segment_ids, existing_notification_id)
	 */
	public static function from_post( array $post, array $pushly_meta ): ?self {
		try {
			global $wp_version;
			$user = wp_get_current_user();

			$notification                = new self();
			$notification->audience      = new NotificationAudience();
			$notification->template      = new NotificationTemplate();
			$notification->delivery_spec = new NotificationDeliverySpec();
			$notification->meta          = [
				'wordpress_version'    => $wp_version,
				'wordpress_post_id'    => $post['ID'],
				'wordpress_user_id'    => $user->ID,
				'wordpress_user_email' => $user->user_email,
				'plugin_version'       => PUSHLY__PLUGIN_VERSION,
				'php_version'          => PHP_VERSION,
				'post_type'            => $post['post_type'] ?? null,
				'post_status'          => $post['post_status'] ?? null,
				'editor_type'          => $post['editor_type'] ?? null,
			];

			if ( ! empty( $pushly_meta['existing_notification_id'] ) ) {
				$notification->id = (int) $pushly_meta['existing_notification_id'];
			}

			// Audience
			if ( empty( $pushly_meta['segment_ids'] ) ) {
				$notification->audience->all_subscribers = true;
			} else {
				$notification->audience->segment_ids = array_map( 'intval', $pushly_meta['segment_ids'] );
			}

			// Template
			$channels              = new NotificationTemplateChannels();
			$channels->web         = new NotificationTemplateChannelsWeb();
			$channels->web->title       = sanitize_text_field( $post['title'] );
			$channels->web->landing_url = $post['landing_url'];

			if ( ! empty( $post['body'] ) ) {
				$channels->web->body = sanitize_text_field( $post['body'] );
			}

			if ( ! empty( $post['image_id'] ) ) {
				$image = wp_get_attachment_image_src( $post['image_id'], 'large' );
				if ( ! empty( $image ) ) {
					$channels->web->image_url = $image[0];
				}
			}

			$notification->template->channels = $channels;

			if ( ! empty( $post['tag_names'] ) ) {
				$notification->template->keywords = array_unique(
					array_merge( $notification->template->keywords, $post['tag_names'] )
				);
			}

			if ( ! empty( $post['category_names'] ) ) {
				$notification->template->keywords = array_unique(
					array_merge( $notification->template->keywords, $post['category_names'] )
				);
			}

			// Delivery spec
			$notification->delivery_spec->window = 'STANDARD';
			$send_date    = new \DateTime( $post['schedule_date'], new \DateTimeZone( 'utc' ) );
			$current_date = new \DateTimeImmutable( 'now', new \DateTimeZone( 'utc' ) );

			$notification->delivery_spec->type = $send_date <= $current_date ? 'IMMEDIATE' : 'SCHEDULED';
			if ( $notification->delivery_spec->type === 'SCHEDULED' ) {
				$notification->delivery_spec->send_date_utc = $send_date->format( 'c' );
			}

			return $notification;
		} catch ( \Exception $e ) {
			Util::log_to_event_stream( 'notification_build_error', $e->getMessage() );
			return null;
		}
	}
}
