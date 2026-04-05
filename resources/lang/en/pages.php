<?php

return [
	'customers' => [
		'title' => 'Customers',
		'description' => 'Manage customer registry, contacts, and customer requirements.',
		'column_name' => 'Name',
		'column_legal_reg' => 'Reg. number',
		'column_ext_id' => 'External ID',
		'column_dpo_name' => 'DPO Name',
		'column_dpo_email' => 'DPO Email',
		'column_description' => 'Description',
		'column_tags' => 'Tags',
		'column_created_at' => 'Created',
	],

	'my_profile' => [
		'title' => 'My profile',
		'description' => 'Manage your personal details, contact information, and profile settings.',
		'section_personal_info' => 'Personal information',
		'section_contact' => 'Contact details',
		'section_account' => 'Account settings',
		'name_label' => 'Name',
		'email_label' => 'Email',
		'role_label' => 'Role',
		'edit_profile' => 'Edit profile',
		'no_data' => 'No profile information available yet.',
	],

	'my_tasks' => [
		'title' => 'My tasks',
		'description' => 'Overview of your assigned tasks, activities, and controls.',
		'open_tasks' => 'Open tasks',
		'completed_tasks' => 'Completed tasks',
		'overdue_tasks' => 'Overdue tasks',
		'no_tasks' => 'No tasks found.',
		'filter_all' => 'All',
		'filter_open' => 'Open',
		'filter_done' => 'Done',
		'filter_overdue' => 'Overdue',
		'status_open' => 'Open',
		'status_done' => 'Done',
		'status_overdue' => 'Overdue',
	],

	'my_documents' => [
		'title' => 'My documents',
		'description' => 'Your saved documents, reports, and files.',
		'recent_documents' => 'Recent documents',
		'no_documents' => 'No documents found.',
		'upload_document' => 'Upload document',
		'search_placeholder' => 'Search your documents...',
		'file_name' => 'File name',
		'file_type' => 'Type',
		'uploaded_at' => 'Uploaded',
	],

	'settings' => [
		'title' => 'Settings',
		'description' => 'Manage your personal settings, notifications, and system preferences.',
		'section_general' => 'General',
		'section_notifications' => 'Notifications',
		'section_appearance' => 'Appearance',
		'language_label' => 'Language',
		'theme_label' => 'Theme',
		'notifications_label' => 'Notifications',
		'save_changes' => 'Save changes',
		'coming_soon' => 'Settings are being built.',
	],

	'dashboard' => [
		'widgets' => [
			'stats' => 'Key figures',
			'tasks' => 'Tasks',
			'goals' => 'Goals and plans',
			'process' => 'Processes',
			'top_risks' => 'Top risks',
			'risk_matrix' => 'Risk matrix',
		],

		'stats' => [
			'open_activities' => 'Open activities',
			'overdue_controls' => 'Overdue controls',
			'completed_goals' => 'Completed goals',
			'risk_score_avg' => 'Risk score (average)',
			'trend_open_activities' => '+3 this week',
			'trend_overdue_controls' => 'Action required',
			'trend_completed_goals' => '44% complete',
			'trend_risk_score_avg' => 'Down 0.4 since last month',
		],

		'tasks' => [
			'title' => 'To do',
			'items' => [
				'review_dependency_licenses' => 'Review licenses in third-party dependencies',
				'book_rise_workshop' => 'Book RISE workshop (network certification)',
				'check_backups' => 'Check backups',
				'report_kpis' => 'Report key figures',
				'check_cert_se' => 'Check cert.se',
				'check_law_source' => 'Check svenskforfattningssamling.se',
				'monthly_letter' => 'Monthly letter',
				'archive_binders_and_systems' => 'Archive accounting binders and systems',
			],
		],

		'goals' => [
			'title' => 'Company goals',
			'status_achieved' => 'Goal achieved',
			'status_acceptable' => 'Acceptable',
			'status_unacceptable' => 'Unacceptable',
			'items' => [
				'training_sessions' => 'Completed training sessions Segloravagen 24',
				'psychology_cycle_time' => 'Kallekullen Psykologi AB - Reduced lead times',
				'platform_growth' => 'Ledningssystemet.se - Improvements and feature growth',
				'hosted_instances' => 'At least 20 billable hosted instances',
				'ordersystem_start' => 'Ordersystemet.se - Start new development',
				'partner_portal_content' => 'Partner portal - Content development',
			],
		],

		'process' => [
			'title' => 'Process',
			'organizations' => [
				'svestra' => 'Svestra',
				'kallekullen' => 'Kallekullen',
			],
			'steps' => [
				'customer_request' => 'Customer request',
				'needs_analysis' => 'Needs analysis',
				'quotation' => 'Quotation',
				'order' => 'Order',
				'delivery' => 'Delivery',
				'follow_up' => 'Follow-up',
			],
			'description' => 'Svestra is formed from the surnames Svenningsson and Strandberg, based on the business owners\' family names.',
		],

		'risk_overview' => [
			'title' => 'Risk overview',
			'consequences' => ['Insignificant', 'Minor', 'Moderate', 'Significant', 'Severe'],
			'likelihoods' => ['Very likely', 'Likely', 'Possible', 'Unlikely'],
		],

		'top_risks' => [
			'title' => '10 highest risks',
			'items' => [
				'portal_intrusion' => 'In the event of an intrusion in portal1.ledningssystemet.se (Admin portal clusterno0)',
				'first_aid' => 'The organization has insufficient ability to provide first aid',
				'fire_drill' => 'Missing evacuation drills at business location Fritsla',
				'backup_prod' => 'Backup solution does not work for Ledningssystemet.se Production',
				'intrusion_awareness' => 'In the event of an intrusion in Ledningssystemet.se Production we do not know what happened',
				'backup_server' => 'Backup solution does not work for backup server',
				'backup_vulns' => 'Known vulnerabilities in backup server',
				'threat_monitoring' => 'Threats targeting Ledningssystemet.se Production do not reach the organization',
			],
		],
	],
];
