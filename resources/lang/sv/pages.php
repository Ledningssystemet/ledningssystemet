<?php

return [
	'dashboard' => [
		'widgets' => [
			'stats' => 'Nyckeltal',
			'tasks' => 'Uppgifter',
			'goals' => 'Mal och planer',
			'process' => 'Processer',
			'top_risks' => 'Topprisker',
			'risk_matrix' => 'Riskmatris',
		],

		'stats' => [
			'open_activities' => 'Oppna aktiviteter',
			'overdue_controls' => 'Forfallna kontroller',
			'completed_goals' => 'Avslutade mal',
			'risk_score_avg' => 'Riskpoang (medel)',
			'trend_open_activities' => '+3 denna vecka',
			'trend_overdue_controls' => 'Krav er atgard',
			'trend_completed_goals' => '44% klart',
			'trend_risk_score_avg' => 'Ner 0.4 senaste manaden',
		],

		'tasks' => [
			'title' => 'Att gora',
			'items' => [
				'review_dependency_licenses' => 'Granska licenser i tredjepartsberoenden',
				'book_rise_workshop' => 'Boka upp RISE workshop (Natverkscertifiering)',
				'check_backups' => 'Kontrollera sakerhetskopior',
				'report_kpis' => 'Rapportera in nyckeltal',
				'check_cert_se' => 'Kontrollera cert.se',
				'check_law_source' => 'Kontrollera svenskforfattningssamling.se',
				'monthly_letter' => 'Manadsbrev',
				'archive_binders_and_systems' => 'Gallra bokforingsparmar och system',
			],
		],

		'goals' => [
			'title' => 'Bolagsmal',
			'status_achieved' => 'Mal uppnatt',
			'status_acceptable' => 'Acceptabel',
			'status_unacceptable' => 'Oacceptabel',
			'items' => [
				'training_sessions' => 'Genomforda traningspass Segloravagen 24',
				'psychology_cycle_time' => 'Kallekullen Psykologi AB - Minskade ledtider',
				'platform_growth' => 'Ledningssystemet.se - Forbattringar och funktionstillvaxt',
				'hosted_instances' => 'Minst 20 debiterbara hostade instanser',
				'ordersystem_start' => 'Ordersystemet.se - Uppstart nyutveckling',
				'partner_portal_content' => 'Partnerportal - Utveckling av innehall',
			],
		],

		'process' => [
			'title' => 'Process',
			'organizations' => [
				'svestra' => 'Svestra',
				'kallekullen' => 'Kallekullen',
			],
			'steps' => [
				'customer_request' => 'Kundforfragan',
				'needs_analysis' => 'Behovsanalys',
				'quotation' => 'Offert',
				'order' => 'Order',
				'delivery' => 'Leverans',
				'follow_up' => 'Uppfoljning',
			],
			'description' => 'Svestra ar sammansattningen av namnen Svenningsson och Strandberg, detta utifran verksamhetsinnehavarnas efternamn.',
		],

		'risk_overview' => [
			'title' => 'Riskoversikt',
			'consequences' => ['Obetydlig', 'Lindrig', 'Mattlig', 'Betydande', 'Allvarlig'],
			'likelihoods' => ['Mycket troligt', 'Troligt', 'Mojligt', 'Osannolikt'],
		],

		'top_risks' => [
			'title' => '10 hogsta riskerna',
			'items' => [
				'portal_intrusion' => 'I handelse av ett intrang i portal1.ledningssystemet.se (Adminportal clusterno0)',
				'first_aid' => 'Verksamheten har otillracklig formaga att ge forsta hjalpen',
				'fire_drill' => 'Ej utforda utrymningsovningar vid verksamhetsstalle Fritsla',
				'backup_prod' => 'Backuplosning fungerar ej for Ledningssystemet.se Produktion',
				'intrusion_awareness' => 'I handelse av ett intrang i Ledningssystemet.se Produktion vet vi inte vad som skett',
				'backup_server' => 'Backuplosning fungerar ej for Backupserver',
				'backup_vulns' => 'Kanda sarbarheter i Backupserver',
				'threat_monitoring' => 'Hot riktade mot Ledningssystemet.se Produktion kommer ej till verksamhetens kannedom',
			],
		],
	],
];

