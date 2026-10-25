<?php

namespace Ledningssystemet\Ledningssystemet\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Ledningssystemet\Ledningssystemet\Models\Project;
use Ledningssystemet\Ledningssystemet\Models\ProjectActivity;
use Ledningssystemet\Ledningssystemet\Models\ProjectMeeting;
use Ledningssystemet\Ledningssystemet\Models\ProjectMeetingMention;
use Ledningssystemet\Ledningssystemet\Models\ProjectRisk;
use Ledningssystemet\Ledningssystemet\Models\ProjectWorkPackage;
use Ledningssystemet\Ledningssystemet\Policies\ProjectActivityPolicy;
use Ledningssystemet\Ledningssystemet\Policies\ProjectItemPolicy;
use Ledningssystemet\Ledningssystemet\Policies\ProjectMeetingMentionPolicy;
use Ledningssystemet\Ledningssystemet\Policies\ProjectPolicy;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        /* Project module */
        Project::class => ProjectPolicy::class,
        ProjectActivity::class => ProjectActivityPolicy::class,
        ProjectMeeting::class => ProjectItemPolicy::class,
        ProjectMeetingMention::class => ProjectMeetingMentionPolicy::class,
        ProjectRisk::class => ProjectItemPolicy::class,
        ProjectWorkPackage::class => ProjectItemPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();
    }
}
