<?php

namespace App\Helpers;


class ElasticQueryBuilder
{
    protected array $must = [];
    protected array $filter = [];


    public function addTerm(string $field, $value): self
    {
        if ($value !== null && $value !== '') {
            $this->filter[] = [
                'term' => [$field => is_numeric($value) ? (int) $value : $value]
            ];
        }
        return $this;
    }

    public function addNestedTerm(string $path, string $field, $value): self
    {
        if ($value !== null && $value !== '') {
            $this->filter[] = [
                'nested' => [
                    'path' => $path,
                    'query' => [
                        'term' => [$field => is_numeric($value) ? (int) $value : $value],
                    ],
                ],
            ];
        }

        return $this;
    }

    public function addMatchPhrase(string $field, $value): self
    {
        if ($value !== null && $value !== '') {
            $this->must[] = ['match_phrase' => [$field => $value]];
        }
        return $this;
    }

    public function addBoolShould(array $conditions, int $min = 1): self
    {
        $this->must[] = [
            'bool' => [
                'should' => $conditions,
                'minimum_should_match' => $min,
            ]
        ];
        return $this;
    }

    public function addRange(string $field, ?string $start = null, ?string $end = null): self
    {
        if (!$start && !$end) return $this;

        $range = [];
        if ($start) $range['gte'] = $start;
        if ($end) $range['lte'] = $end;

        $this->filter[] = ['range' => [$field => $range]];

        return $this;
    }

    public function addExistsNot(string $field): self
    {
        $this->filter[] = [
            'bool' => [
                'must_not' => [
                    ['exists' => ['field' => $field]],
                ],
            ],
        ];
        return $this;
    }

    public function addUserFilter(int $roleId, string $field, $value): self
    {
        if ($value === null || $value === '') {
            return $this;
        }

        $this->must[] = [
            'nested' => [
                'path' => 'users',
                'query' => [
                    'bool' => [
                        'must' => [
                            [
                                'term' => [
                                    "$field" => (string)$value
                                ]
                            ],
                            [
                                'nested' => [
                                    'path' => 'users.role',
                                    'query' => [
                                        'term' => [
                                            'users.role.id' => $roleId
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                'inner_hits' => new \stdClass(),
            ]
        ];

        return $this;
    }

    public function addMultiMatch(?string $query, array $fields): self
    {
        if ($query === null || $query === '') return $this;

        $this->filter[] = [
            'multi_match' => [
                'query' => $query,
                'fields' => $fields,
            ]
        ];

        return $this;
    }

    public function build(): array
    {
        return [
            'bool' => [
                'must' => $this->must,
                'filter' => $this->filter,
            ]
        ];
    }
}
