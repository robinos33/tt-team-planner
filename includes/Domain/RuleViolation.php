<?php
declare(strict_types=1);

namespace TT\TeamPlanner\Domain;

final class RuleViolation
{
    public const SEV_HIGH = 'high';
    public const SEV_MED  = 'med';

    public function __construct(
        public readonly string $type,
        public readonly string $severity,
        public readonly string $title,
        public readonly string $detail,
        public readonly string $teamCode,
        public readonly int    $journee,
        public readonly array  $playerIds = [],
    ) {}

    public function toArray(): array
    {
        return [
            'type'       => $this->type,
            'sev'        => $this->severity,
            'title'      => $this->title,
            'detail'     => $this->detail,
            'team_code'  => $this->teamCode,
            'journee'    => $this->journee,
            'player_ids' => $this->playerIds,
            'player_id'  => $this->playerIds[0] ?? null,
        ];
    }
}
