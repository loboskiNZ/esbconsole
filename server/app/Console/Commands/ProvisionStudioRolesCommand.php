<?php

namespace App\Console\Commands;

use App\Services\StudioRoleProvisioner;
use Illuminate\Console\Command;

class ProvisionStudioRolesCommand extends Command
{
    protected $signature = 'esb:provision-studio-roles
                            {--band-id= : Band scope for director assignment}';

    protected $description = 'Provision Cloud Studio system roles and assign the platform director non-destructively';

    public function handle(StudioRoleProvisioner $provisioner): int
    {
        $bandId = $this->option('band-id') !== null
            ? (int) $this->option('band-id')
            : null;

        $result = $provisioner->provision($bandId);

        $this->line('Users before: '.$result['users_count_before']);
        $this->line('Users after: '.$result['users_count_after']);
        $this->line('Roles created: '.$result['roles_created']);
        $this->line('Director user id: '.($result['director_user_id'] ?? 'not found'));
        $this->line('Director assigned: '.($result['director_assigned'] ? 'yes' : 'no'));

        foreach ($provisioner->systemRoles() as $role) {
            $this->info("OK role {$role->code} ({$role->name})");
        }

        return self::SUCCESS;
    }
}
