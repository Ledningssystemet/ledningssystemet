import { execSync } from 'node:child_process';
import type { FullConfig } from '@playwright/test';

export default async function globalSetup(_config: FullConfig): Promise<void> {
    // Ensure every e2e run starts from a clean, seeded testing database.
    execSync('php artisan --env=testing migrate:fresh --seed --force', {
        stdio: 'inherit',
    });
}

