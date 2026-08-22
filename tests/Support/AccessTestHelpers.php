<?php

namespace Tests\Support;

use Illuminate\Support\Collection;
use Webkul\Contact\Models\Organization;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Source;
use Webkul\Contact\Models\Person;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

class AccessTestHelpers
{
    public static function source(int $id): Source
    {
        $source = new Source;

        $source->forceFill(['id' => $id, 'name' => "Source {$id}"]);

        return $source;
    }

    public static function organization(int $id): Organization
    {
        $organization = new Organization;

        $organization->forceFill(['id' => $id, 'name' => "Company {$id}"]);

        return $organization;
    }

    public static function user(array $options = []): User
    {
        $user = new User;

        $user->forceFill([
            'id'    => $options['id'] ?? 2,
            'name'  => $options['name'] ?? 'Test User',
            'email' => $options['email'] ?? 'test@example.com',
        ]);

        $role = new Role;

        $role->forceFill([
            'id'              => $options['role_id'] ?? 2,
            'name'            => $options['role_name'] ?? 'Custom Role',
            'permission_type' => ($options['admin'] ?? false) ? 'all' : 'custom',
        ]);

        $role->setRelation(
            'sources',
            collect($options['role_source_ids'] ?? [])->map(fn (int $id) => self::source($id))
        );

        $role->setRelation(
            'organizations',
            collect($options['role_organization_ids'] ?? [])->map(fn (int $id) => self::organization($id))
        );

        if (array_key_exists('role_pipeline_stage_ids', $options)) {
            $role->setRelation(
                'pipelineStages',
                collect($options['role_pipeline_stage_ids'])->map(function (int $id) {
                    $stage = new \Webkul\Lead\Models\Stage;
                    $stage->forceFill(['id' => $id]);

                    return $stage;
                })
            );
        }

        $user->setRelation('role', $role);

        $user->setRelation(
            'sources',
            collect($options['user_source_ids'] ?? [])->map(fn (int $id) => self::source($id))
        );

        $user->setRelation(
            'organizations',
            collect($options['user_organization_ids'] ?? [])->map(fn (int $id) => self::organization($id))
        );

        return $user;
    }

    public static function lead(array $options = []): Lead
    {
        $lead = new Lead;

        $lead->forceFill([
            'id'                 => $options['id'] ?? 1,
            'title'              => $options['title'] ?? 'Test Lead',
            'user_id'            => array_key_exists('user_id', $options)
                ? $options['user_id']
                : ($options['owner_user_id'] ?? 2),
            'lead_owner_id'      => $options['lead_owner_id'] ?? null,
            'lead_source_id'     => $options['lead_source_id'] ?? null,
            'lead_sub_source_id' => $options['lead_sub_source_id'] ?? null,
            'lead_pipeline_stage_id' => $options['lead_pipeline_stage_id'] ?? null,
            'closed_at'          => $options['closed_at'] ?? null,
            'person_id'          => $options['person_id'] ?? (array_key_exists('organization_id', $options) ? 1 : null),
        ]);

        if (array_key_exists('organization_id', $options)) {
            $person = new Person;

            $person->forceFill([
                'id'              => $options['person_id'] ?? 1,
                'organization_id' => $options['organization_id'],
            ]);

            $lead->setRelation('person', $person);
        }

        return $lead;
    }
}
