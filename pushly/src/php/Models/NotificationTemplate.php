<?php

namespace Pushly\Models;

class NotificationTemplate implements \JsonSerializable {
	public ?NotificationTemplateChannels $channels = null;
	public array $keywords = [];

	#[\ReturnTypeWillChange]
	public function jsonSerialize(): array {
		return [
			'channels' => $this->channels,
			'keywords' => $this->keywords,
		];
	}
}
