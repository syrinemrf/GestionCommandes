<?php

namespace App\Dto\Analytics;

use Symfony\Component\HttpFoundation\Request;

final readonly class AnalyticsDateRange
{
    private const MAX_RANGE_DAYS = 730;

    public function __construct(
        public \DateTimeImmutable $from,
        public \DateTimeImmutable $to,
    ) {
        if ($from > $to) {
            throw new \InvalidArgumentException(
                'The "from" date must be before or equal to "to".'
            );
        }

        if ($from->diff($to)->days > self::MAX_RANGE_DAYS) {
            throw new \InvalidArgumentException(
                'The requested date range cannot exceed 731 days.'
            );
        }
    }

    public static function fromRequest(Request $request): self
    {
        $toValue = trim((string) $request->query->get('to', ''));
        $to = $toValue === ''
            ? new \DateTimeImmutable('today')
            : self::parseDate($toValue, 'to');

        $fromValue = trim((string) $request->query->get('from', ''));
        $from = $fromValue === ''
            ? $to->modify('-29 days')
            : self::parseDate($fromValue, 'from');

        return new self($from, $to);
    }

    public static function limitFromRequest(
        Request $request,
        int $default,
        int $maximum,
    ): int {
        $value = trim((string) $request->query->get('limit', ''));
        if ($value === '') {
            return $default;
        }
        if (!ctype_digit($value)) {
            throw new \InvalidArgumentException(
                'The "limit" parameter must be a positive integer.'
            );
        }

        $limit = (int) $value;
        if ($limit < 1 || $limit > $maximum) {
            throw new \InvalidArgumentException(sprintf(
                'The "limit" parameter must be between 1 and %d.',
                $maximum
            ));
        }

        return $limit;
    }

    public function toArray(): array
    {
        return [
            'from' => $this->from->format('Y-m-d'),
            'to' => $this->to->format('Y-m-d'),
        ];
    }

    public function previous(): self
    {
        $days = $this->from->diff($this->to)->days + 1;
        $previousTo = $this->from->modify('-1 day');

        return new self(
            $previousTo->modify(sprintf('-%d days', $days - 1)),
            $previousTo,
        );
    }

    private static function parseDate(string $value, string $parameter): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();
        if (
            !$date
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $value
        ) {
            throw new \InvalidArgumentException(sprintf(
                'The "%s" parameter must use the YYYY-MM-DD format.',
                $parameter
            ));
        }

        return $date;
    }
}
