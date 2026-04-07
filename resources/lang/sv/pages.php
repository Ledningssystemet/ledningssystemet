<?php

return [
	'dashboard' => [
		'shared' => [
			'loading' => 'Laddar dashboarddata...',
			'load_error' => 'Kunde inte ladda dashboarddata.',
		],

		'widgets' => [
			'stats' => 'Nyckeltal',
			'tasks' => 'Uppgifter',
			'goals' => 'Mal och planer',
			'process' => 'Processer',
			'top_risks' => 'Hogsta risker',
			'risk_matrix' => 'Riskmatris',
		],

		'stats' => [
			'open_activities' => 'Oppna aktiviteter',
			'overdue_controls' => 'Forsenade kontroller',
			'completed_goals' => 'Uppnadda mal',
			'risk_score_avg' => 'Riskpoang (genomsnitt)',
			'trend_open_activities' => '+3 denna vecka',
			'trend_overdue_controls' => 'Atgard kravs',
			'trend_completed_goals' => '44 % klart',
			'trend_risk_score_avg' => 'Ner 0,4 sedan forra manaden',
		],

		'tasks' => [
			'title' => 'Att gora',
			'no_tasks' => 'Inga uppgifter att visa.',
		],

		'goals' => [
			'title' => 'Foretagsmal',
			'no_goals' => 'Inga mal att visa.',
			'status_achieved' => 'Mal uppnatt',
			'status_acceptable' => 'Acceptabelt',
			'status_unacceptable' => 'Inte acceptabelt',
		],

		'process' => [
			'title' => 'Process',
			'fullscreen' => 'Oppna helskarm',
			'fullscreen_title' => 'Processkarta',
			'fit_to_screen' => 'Anpassa till skarm',
			'start_process' => 'start',
			'unassigned_department' => 'Ingen avdelning',
			'zoom_hint' => 'Anvand mushjul och dra for att navigera kartan.',
			'no_processes' => 'Inga processer tillgangliga.',
			'no_steps' => 'Inga processaktiviteter hittades for denna process.',
			'no_published_bpmn' => 'Ingen publicerad processkarta finns for den har processen.',
			'invalid_bpmn' => 'Den publicerade processkartan kunde inte renderas.',
			'set_as_preferred_process' => 'Valj som foretredd process',
			'set_as_preferred_process_tooltip' => 'Visa denna process i processkortet nar du oppnar instrumentpanelen',
			'reset_preferred_process' => 'Aterstall startprocess',
			'reset_preferred_process_tooltip' => 'Ta bort ditt eget val av startprocess och ga tillbaka till standard',
			'preferred_process_saved' => ':process ar nu din foretredd process',
			'show_details' => 'Visa detaljer',
		],

		'risk_overview' => [
			'title' => 'Riskoversikt',
			'no_configuration' => 'Ingen konfiguration for riskmatris tillganglig.',
		],

		'top_risks' => [
			'title' => '10 hogsta riskerna',
			'no_risks' => 'Inga risker att visa.',
		],
	],

	'settings' => [
		'title' => 'Installningar',
		'description' => 'Hantera dina personliga installningar, notifieringar och systemval.',
		'section_general' => 'Allmant',
		'section_notifications' => 'Notifieringar',
		'language_label' => 'Sprak',
		'theme_label' => 'Tema',
		'coming_soon' => 'Installningar ar under uppbyggnad.',
		'start_page_label' => 'Startsida',
		'start_page_help' => 'Valj vilken sida som ska oppnas som standard nar du gar in i appen.',
		'start_page_save_button' => 'Spara startsida',
		'start_page_saved' => 'Startsidan har sparats.',
		'start_page_pending_changes' => 'Du har osparade andringar. Klicka pa knappen nedan for att spara startsidan.',
		'start_page_no_pending_changes' => 'Nuvarande startsida ar redan sparad.',
	],
];
