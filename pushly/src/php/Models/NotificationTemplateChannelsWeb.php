<?php

namespace Pushly\Models;

class NotificationTemplateChannelsWeb implements \JsonSerializable {
	public ?string $title = null;
	public ?string $body = null;
	public ?string $landing_url = null;
	public ?string $image_url = null;

	#[\ReturnTypeWillChange]
	public function jsonSerialize(): array {
		return [
			'title'       => $this->title,
			'body'        => $this->body,
			'landing_url' => $this->landing_url,
			'image_url'   => $this->image_url,
		];
	}
}
