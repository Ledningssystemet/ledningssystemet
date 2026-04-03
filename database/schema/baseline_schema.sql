SET
FOREIGN_KEY_CHECKS=0;


CREATE TABLE `access_group_user` (
                                     `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                     `access_group_id` bigint(20) unsigned NOT NULL,
                                     `user_id` bigint(20) unsigned NOT NULL,
                                     `created_at` timestamp NULL DEFAULT NULL,
                                     `updated_at` timestamp NULL DEFAULT NULL,
                                     PRIMARY KEY (`id`),
                                     KEY `access_group_user_access_group_id_foreign` (`access_group_id`),
                                     KEY `access_group_user_user_id_foreign` (`user_id`),
                                     CONSTRAINT `access_group_user_access_group_id_foreign` FOREIGN KEY (`access_group_id`) REFERENCES `access_groups` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                     CONSTRAINT `access_group_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `access_groups` (
                                 `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                 `name` varchar(255) NOT NULL,
                                 `claims` longtext DEFAULT NULL CHECK (json_valid(`claims`)),
                                 `risk_level_id` bigint(20) unsigned DEFAULT NULL,
                                 `created_at` timestamp NULL DEFAULT NULL,
                                 `updated_at` timestamp NULL DEFAULT NULL,
                                 `external_provider_group_id` bigint(20) unsigned DEFAULT NULL,
                                 PRIMARY KEY (`id`),
                                 UNIQUE KEY `access_groups_name_unique` (`name`),
                                 KEY `access_groups_risk_level_id_foreign` (`risk_level_id`),
                                 KEY `access_groups_external_provider_group_id_foreign` (`external_provider_group_id`),
                                 CONSTRAINT `access_groups_external_provider_group_id_foreign` FOREIGN KEY (`external_provider_group_id`) REFERENCES `external_provider_groups` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                 CONSTRAINT `access_groups_risk_level_id_foreign` FOREIGN KEY (`risk_level_id`) REFERENCES `risk_levels` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `activities` (
                              `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                              `uid` char(36) DEFAULT NULL,
                              `name` varchar(255) NOT NULL,
                              `description` text NOT NULL,
                              `due` date NOT NULL,
                              `intervalnum` int(10) unsigned NOT NULL DEFAULT 0,
                              `intervaltype` varchar(255) DEFAULT NULL,
                              `completed_at` datetime DEFAULT NULL,
                              `created_at` timestamp NULL DEFAULT NULL,
                              `updated_at` timestamp NULL DEFAULT NULL,
                              `responsible_user_id` bigint(20) unsigned DEFAULT NULL,
                              `partner_id` bigint(20) unsigned DEFAULT NULL,
                              `partner_object_uid` char(36) DEFAULT NULL,
                              `partner_object_updated_at` timestamp NULL DEFAULT NULL,
                              `activity_flow_id` bigint(20) unsigned DEFAULT NULL,
                              `activity_flow_template_item_id` bigint(20) unsigned DEFAULT NULL,
                              PRIMARY KEY (`id`),
                              UNIQUE KEY `activities_uid_unique` (`uid`),
                              KEY `activities_responsible_user_id_foreign` (`responsible_user_id`),
                              KEY `activities_partner_id_foreign` (`partner_id`),
                              KEY `activities_activity_flow_id_foreign` (`activity_flow_id`),
                              KEY `activities_activity_flow_template_item_id_foreign` (`activity_flow_template_item_id`),
                              CONSTRAINT `activities_activity_flow_id_foreign` FOREIGN KEY (`activity_flow_id`) REFERENCES `activity_flows` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                              CONSTRAINT `activities_activity_flow_template_item_id_foreign` FOREIGN KEY (`activity_flow_template_item_id`) REFERENCES `activity_flow_template_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                              CONSTRAINT `activities_partner_id_foreign` FOREIGN KEY (`partner_id`) REFERENCES `partners` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                              CONSTRAINT `activities_responsible_user_id_foreign` FOREIGN KEY (`responsible_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `activity_flow_template_items` (
                                                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                                `name` varchar(255) NOT NULL,
                                                `type` enum('header','item') NOT NULL DEFAULT 'item',
                                                `ordinal` int(10) unsigned NOT NULL DEFAULT 0,
                                                `description` text DEFAULT NULL,
                                                `waitforpreceeding` tinyint(1) NOT NULL DEFAULT 0,
                                                `dueoffsetdays` int(10) unsigned NOT NULL DEFAULT 0,
                                                `activity_flow_template_id` bigint(20) unsigned NOT NULL,
                                                `partner_object_uid` char(36) DEFAULT NULL,
                                                `partner_object_updated_at` timestamp NULL DEFAULT NULL,
                                                `created_at` timestamp NULL DEFAULT NULL,
                                                `updated_at` timestamp NULL DEFAULT NULL,
                                                PRIMARY KEY (`id`),
                                                UNIQUE KEY `activity_flow_template_items_partner_object_uid_unique` (`partner_object_uid`),
                                                KEY `activity_flow_template_items_activity_flow_template_id_foreign` (`activity_flow_template_id`),
                                                CONSTRAINT `activity_flow_template_items_activity_flow_template_id_foreign` FOREIGN KEY (`activity_flow_template_id`) REFERENCES `activity_flow_templates` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `activity_flow_templates` (
                                           `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                           `name` varchar(255) NOT NULL,
                                           `description` text DEFAULT NULL,
                                           `user_instantiatable` tinyint(1) NOT NULL DEFAULT 0,
                                           `partner_id` bigint(20) unsigned DEFAULT NULL,
                                           `partner_object_uid` char(36) DEFAULT NULL,
                                           `partner_object_updated_at` timestamp NULL DEFAULT NULL,
                                           `created_at` timestamp NULL DEFAULT NULL,
                                           `updated_at` timestamp NULL DEFAULT NULL,
                                           PRIMARY KEY (`id`),
                                           UNIQUE KEY `activity_flow_templates_partner_object_uid_unique` (`partner_object_uid`),
                                           KEY `activity_flow_templates_partner_id_foreign` (`partner_id`),
                                           CONSTRAINT `activity_flow_templates_partner_id_foreign` FOREIGN KEY (`partner_id`) REFERENCES `partners` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `activity_flows` (
                                  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                  `name` varchar(255) NOT NULL,
                                  `description` text DEFAULT NULL,
                                  `responsible_user_id` bigint(20) unsigned NOT NULL,
                                  `activity_flow_template_id` bigint(20) unsigned NOT NULL,
                                  `started_at` timestamp NULL DEFAULT NULL,
                                  `created_at` timestamp NULL DEFAULT NULL,
                                  `updated_at` timestamp NULL DEFAULT NULL,
                                  PRIMARY KEY (`id`),
                                  KEY `activity_flows_responsible_user_id_foreign` (`responsible_user_id`),
                                  KEY `af_afti` (`activity_flow_template_id`),
                                  CONSTRAINT `activity_flows_responsible_user_id_foreign` FOREIGN KEY (`responsible_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                  CONSTRAINT `af_afti` FOREIGN KEY (`activity_flow_template_id`) REFERENCES `activity_flow_templates` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `agreements` (
                              `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                              `name` varchar(255) NOT NULL,
                              `description` text DEFAULT NULL,
                              `startdate` date DEFAULT NULL,
                              `enddate` date DEFAULT NULL,
                              `reminderdate` date DEFAULT NULL,
                              `responsible_user_id` bigint(20) unsigned DEFAULT NULL,
                              `supplier_id` bigint(20) unsigned DEFAULT NULL,
                              `customer_id` bigint(20) unsigned DEFAULT NULL,
                              `created_at` timestamp NULL DEFAULT NULL,
                              `updated_at` timestamp NULL DEFAULT NULL,
                              `archived_at` timestamp NULL DEFAULT NULL,
                              PRIMARY KEY (`id`),
                              KEY `agreements_responsible_user_id_foreign` (`responsible_user_id`),
                              KEY `agreements_supplier_id_foreign` (`supplier_id`),
                              KEY `agreements_customer_id_foreign` (`customer_id`),
                              CONSTRAINT `agreements_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                              CONSTRAINT `agreements_responsible_user_id_foreign` FOREIGN KEY (`responsible_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                              CONSTRAINT `agreements_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `ai_queries` (
                              `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                              `context` varchar(255) NOT NULL,
                              `user_id` bigint(20) unsigned DEFAULT NULL,
                              `model` varchar(255) NOT NULL,
                              `prompt_tokens` bigint(20) unsigned NOT NULL,
                              `completion_tokens` bigint(20) unsigned NOT NULL,
                              `total_tokens` bigint(20) unsigned NOT NULL,
                              `created_at` timestamp NULL DEFAULT NULL,
                              `updated_at` timestamp NULL DEFAULT NULL,
                              PRIMARY KEY (`id`),
                              KEY `aq_ui` (`user_id`),
                              CONSTRAINT `aq_ui` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `announcements` (
                                 `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                 `remote_id` bigint(20) unsigned NOT NULL,
                                 `severity` enum('info','warning','danger') NOT NULL,
                                 `header` varchar(255) NOT NULL,
                                 `description` mediumtext NOT NULL,
                                 `visible_until` date NOT NULL,
                                 `created_at` timestamp NULL DEFAULT NULL,
                                 `updated_at` timestamp NULL DEFAULT NULL,
                                 PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `asset_asset_dependancy` (
                                          `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                          `dependant_asset_id` bigint(20) unsigned NOT NULL,
                                          `depending_asset_id` bigint(20) unsigned NOT NULL,
                                          `inherit_confidentiality` tinyint(1) NOT NULL DEFAULT 0,
                                          `inherit_integrity` tinyint(1) NOT NULL DEFAULT 0,
                                          `inherit_availability` tinyint(1) NOT NULL DEFAULT 0,
                                          `created_at` timestamp NULL DEFAULT NULL,
                                          `updated_at` timestamp NULL DEFAULT NULL,
                                          `description` longtext DEFAULT NULL,
                                          PRIMARY KEY (`id`),
                                          KEY `aad_dependantasset_fk` (`dependant_asset_id`),
                                          KEY `aad_dependingasset_fk` (`depending_asset_id`),
                                          CONSTRAINT `aad_dependantasset_fk` FOREIGN KEY (`dependant_asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                          CONSTRAINT `aad_dependingasset_fk` FOREIGN KEY (`depending_asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `asset_information_type` (
                                          `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                          `asset_id` bigint(20) unsigned NOT NULL,
                                          `information_type_id` bigint(20) unsigned NOT NULL,
                                          `process_id` bigint(20) unsigned NOT NULL,
                                          PRIMARY KEY (`id`),
                                          KEY `asset_information_type_asset_id_foreign` (`asset_id`),
                                          KEY `asset_information_type_information_type_id_foreign` (`information_type_id`),
                                          KEY `asset_information_type_process_id_foreign` (`process_id`),
                                          CONSTRAINT `asset_information_type_asset_id_foreign` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                          CONSTRAINT `asset_information_type_information_type_id_foreign` FOREIGN KEY (`information_type_id`) REFERENCES `information_types` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                          CONSTRAINT `asset_information_type_process_id_foreign` FOREIGN KEY (`process_id`) REFERENCES `processes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `assets` (
                          `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                          `uid` char(36) DEFAULT NULL,
                          `name` varchar(255) NOT NULL,
                          `description` longtext DEFAULT NULL,
                          `responsible_user_id` bigint(20) unsigned DEFAULT NULL,
                          `created_at` timestamp NULL DEFAULT NULL,
                          `updated_at` timestamp NULL DEFAULT NULL,
                          `supplier_id` bigint(20) unsigned DEFAULT NULL,
                          `confidentiality_class_id` bigint(20) unsigned DEFAULT NULL,
                          `integrity_class_id` bigint(20) unsigned DEFAULT NULL,
                          `availability_class_id` bigint(20) unsigned DEFAULT NULL,
                          `mtd` int(10) unsigned DEFAULT NULL,
                          `rpo` int(10) unsigned DEFAULT NULL,
                          `site_id` bigint(20) unsigned DEFAULT NULL,
                          PRIMARY KEY (`id`),
                          UNIQUE KEY `assets_name_unique` (`name`),
                          UNIQUE KEY `assets_uid_unique` (`uid`),
                          KEY `assets_responsible_user_id_foreign` (`responsible_user_id`),
                          KEY `assets_supplier_id_foreign` (`supplier_id`),
                          KEY `assets_confidentiality_class_id_foreign` (`confidentiality_class_id`),
                          KEY `assets_integrity_class_id_foreign` (`integrity_class_id`),
                          KEY `assets_availability_class_id_foreign` (`availability_class_id`),
                          KEY `asi_fk` (`site_id`),
                          CONSTRAINT `asi_fk` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                          CONSTRAINT `assets_availability_class_id_foreign` FOREIGN KEY (`availability_class_id`) REFERENCES `availability_classes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                          CONSTRAINT `assets_confidentiality_class_id_foreign` FOREIGN KEY (`confidentiality_class_id`) REFERENCES `confidentiality_classes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                          CONSTRAINT `assets_integrity_class_id_foreign` FOREIGN KEY (`integrity_class_id`) REFERENCES `integrity_classes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                          CONSTRAINT `assets_responsible_user_id_foreign` FOREIGN KEY (`responsible_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                          CONSTRAINT `assets_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `availability_classes` (
                                        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                        `name` varchar(255) NOT NULL,
                                        `description` text NOT NULL,
                                        `ordinal` int(10) unsigned NOT NULL DEFAULT 0,
                                        `created_at` timestamp NULL DEFAULT NULL,
                                        `updated_at` timestamp NULL DEFAULT NULL,
                                        PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `chemicals` (
                             `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                             `name` varchar(255) NOT NULL,
                             `manufacturer` varchar(255) DEFAULT NULL,
                             `description` mediumtext DEFAULT NULL,
                             `usagedescription` mediumtext DEFAULT NULL,
                             `storagedescription` mediumtext DEFAULT NULL,
                             `consumptiondescription` mediumtext DEFAULT NULL,
                             `riskdescription` mediumtext DEFAULT NULL,
                             `handlingguidance` mediumtext DEFAULT NULL,
                             `ohs_danger_properties` bigint(20) unsigned NOT NULL DEFAULT 0,
                             `sdbfilename` varchar(255) DEFAULT NULL,
                             `sdbcontenttype` varchar(255) DEFAULT NULL,
                             `sdbcontentlength` bigint(20) unsigned DEFAULT NULL,
                             `created_at` timestamp NULL DEFAULT NULL,
                             `updated_at` timestamp NULL DEFAULT NULL,
                             `sdbfilecontent` longblob DEFAULT NULL,
                             PRIMARY KEY (`id`),
                             UNIQUE KEY `chemicals_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `competence_levels` (
                                     `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                     `competence_id` bigint(20) unsigned NOT NULL,
                                     `name` varchar(255) NOT NULL,
                                     `description` longtext DEFAULT NULL,
                                     `ordinal` int(10) unsigned NOT NULL DEFAULT 0,
                                     `created_at` timestamp NULL DEFAULT NULL,
                                     `updated_at` timestamp NULL DEFAULT NULL,
                                     PRIMARY KEY (`id`),
                                     KEY `competence_levels_competence_id_foreign` (`competence_id`),
                                     CONSTRAINT `competence_levels_competence_id_foreign` FOREIGN KEY (`competence_id`) REFERENCES `competences` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `competences` (
                               `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                               `name` varchar(255) NOT NULL,
                               `description` longtext DEFAULT NULL,
                               `created_at` timestamp NULL DEFAULT NULL,
                               `updated_at` timestamp NULL DEFAULT NULL,
                               PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `compliance_evaluation_requirement` (
                                                     `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                                     `compliance_evaluation_id` bigint(20) unsigned NOT NULL,
                                                     `requirement_id` bigint(20) unsigned NOT NULL,
                                                     `cers_id` bigint(20) unsigned NOT NULL,
                                                     `name` varchar(255) NOT NULL,
                                                     `reference` varchar(255) NOT NULL,
                                                     `description` text DEFAULT NULL,
                                                     `governance` text DEFAULT NULL,
                                                     `note` text DEFAULT NULL,
                                                     `evaluated` tinyint(1) NOT NULL DEFAULT 0,
                                                     `applicable` tinyint(1) NOT NULL DEFAULT 0,
                                                     `created_at` timestamp NULL DEFAULT NULL,
                                                     `updated_at` timestamp NULL DEFAULT NULL,
                                                     PRIMARY KEY (`id`),
                                                     KEY `cerceid` (`compliance_evaluation_id`),
                                                     KEY `cersrid` (`cers_id`),
                                                     KEY `cerrid` (`requirement_id`),
                                                     CONSTRAINT `cerceid` FOREIGN KEY (`compliance_evaluation_id`) REFERENCES `compliance_evaluations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                                     CONSTRAINT `cerrid` FOREIGN KEY (`requirement_id`) REFERENCES `requirements` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                                     CONSTRAINT `cersrid` FOREIGN KEY (`cers_id`) REFERENCES `compliance_evaluation_requirement_source` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `compliance_evaluation_requirement_findings` (
                                                              `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                                              `compliance_evaluation_requirement_id` bigint(20) unsigned NOT NULL,
                                                              `department_id` bigint(20) unsigned DEFAULT NULL,
                                                              `name` varchar(100) NOT NULL,
                                                              `description` text DEFAULT NULL,
                                                              `isnc` tinyint(1) NOT NULL DEFAULT 0,
                                                              `created_at` timestamp NULL DEFAULT NULL,
                                                              `updated_at` timestamp NULL DEFAULT NULL,
                                                              PRIMARY KEY (`id`),
                                                              KEY `cerfcerid` (`compliance_evaluation_requirement_id`),
                                                              KEY `cerfdepid` (`department_id`),
                                                              CONSTRAINT `cerfcerid` FOREIGN KEY (`compliance_evaluation_requirement_id`) REFERENCES `compliance_evaluation_requirement` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                                              CONSTRAINT `cerfdepid` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `compliance_evaluation_requirement_source` (
                                                            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                                            `requirement_source_id` bigint(20) unsigned NOT NULL,
                                                            `compliance_evaluation_id` bigint(20) unsigned NOT NULL,
                                                            `note` text DEFAULT NULL,
                                                            `created_at` timestamp NULL DEFAULT NULL,
                                                            `updated_at` timestamp NULL DEFAULT NULL,
                                                            PRIMARY KEY (`id`),
                                                            KEY `cersrsid` (`requirement_source_id`),
                                                            KEY `cersceid` (`compliance_evaluation_id`),
                                                            CONSTRAINT `cersceid` FOREIGN KEY (`compliance_evaluation_id`) REFERENCES `compliance_evaluations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                                            CONSTRAINT `cersrsid` FOREIGN KEY (`requirement_source_id`) REFERENCES `requirement_sources` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `compliance_evaluations` (
                                          `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                          `uid` char(36) DEFAULT NULL,
                                          `name` varchar(100) NOT NULL,
                                          `startdate` date NOT NULL,
                                          `description` text DEFAULT NULL,
                                          `participants` text DEFAULT NULL,
                                          `summary` text DEFAULT NULL,
                                          `finished` timestamp NULL DEFAULT NULL,
                                          `archived` timestamp NULL DEFAULT NULL,
                                          `created_at` timestamp NULL DEFAULT NULL,
                                          `updated_at` timestamp NULL DEFAULT NULL,
                                          PRIMARY KEY (`id`),
                                          UNIQUE KEY `compliance_evaluations_uid_unique` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `confidentiality_classes` (
                                           `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                           `name` varchar(255) NOT NULL,
                                           `description` text NOT NULL,
                                           `ordinal` int(10) unsigned NOT NULL DEFAULT 0,
                                           `created_at` timestamp NULL DEFAULT NULL,
                                           `updated_at` timestamp NULL DEFAULT NULL,
                                           PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `confidentiality_grounds` (
                                           `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                           `name` varchar(255) NOT NULL,
                                           `description` mediumtext DEFAULT NULL,
                                           `created_at` timestamp NULL DEFAULT NULL,
                                           `updated_at` timestamp NULL DEFAULT NULL,
                                           PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `consequence_levels` (
                                      `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                      `name` varchar(255) NOT NULL,
                                      `description` text NOT NULL,
                                      `ordinal` int(10) unsigned NOT NULL DEFAULT 0,
                                      `created_at` timestamp NULL DEFAULT NULL,
                                      `updated_at` timestamp NULL DEFAULT NULL,
                                      PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `control_action_mappings` (
                                           `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                           `control_action_id` bigint(20) unsigned NOT NULL,
                                           `risk_id` bigint(20) unsigned DEFAULT NULL,
                                           `finding_id` bigint(20) unsigned DEFAULT NULL,
                                           `incident_id` bigint(20) unsigned DEFAULT NULL,
                                           `objective_id` bigint(20) unsigned DEFAULT NULL,
                                           `created_at` timestamp NULL DEFAULT NULL,
                                           `updated_at` timestamp NULL DEFAULT NULL,
                                           PRIMARY KEY (`id`),
                                           KEY `control_action_mappings_control_action_id_foreign` (`control_action_id`),
                                           KEY `control_action_mappings_risk_id_foreign` (`risk_id`),
                                           KEY `control_action_mappings_finding_id_foreign` (`finding_id`),
                                           KEY `control_action_mappings_incident_id_foreign` (`incident_id`),
                                           KEY `control_action_mappings_objective_id_foreign` (`objective_id`),
                                           CONSTRAINT `control_action_mappings_control_action_id_foreign` FOREIGN KEY (`control_action_id`) REFERENCES `control_actions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                           CONSTRAINT `control_action_mappings_finding_id_foreign` FOREIGN KEY (`finding_id`) REFERENCES `findings` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                           CONSTRAINT `control_action_mappings_incident_id_foreign` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                           CONSTRAINT `control_action_mappings_objective_id_foreign` FOREIGN KEY (`objective_id`) REFERENCES `objectives` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                           CONSTRAINT `control_action_mappings_risk_id_foreign` FOREIGN KEY (`risk_id`) REFERENCES `risks` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `control_actions` (
                                   `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                   `name` varchar(255) NOT NULL,
                                   `description` text NOT NULL,
                                   `control_id` bigint(20) unsigned NOT NULL,
                                   `responsible_id` bigint(20) unsigned DEFAULT NULL,
                                   `due` date DEFAULT NULL,
                                   `finished_at` datetime DEFAULT NULL,
                                   `created_at` timestamp NULL DEFAULT NULL,
                                   `updated_at` timestamp NULL DEFAULT NULL,
                                   `originaldue` date DEFAULT NULL,
                                   `estimated_cost` bigint(20) unsigned DEFAULT NULL,
                                   PRIMARY KEY (`id`),
                                   KEY `control_actions_control_id_foreign` (`control_id`),
                                   KEY `control_actions_responsible_id_foreign` (`responsible_id`),
                                   CONSTRAINT `control_actions_control_id_foreign` FOREIGN KEY (`control_id`) REFERENCES `controls` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                   CONSTRAINT `control_actions_responsible_id_foreign` FOREIGN KEY (`responsible_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `control_requirements` (
                                        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                        `control_id` bigint(20) unsigned NOT NULL,
                                        `requirement_id` bigint(20) unsigned NOT NULL,
                                        PRIMARY KEY (`id`),
                                        KEY `control_requirements_control_id_foreign` (`control_id`),
                                        KEY `control_requirements_requirement_id_foreign` (`requirement_id`),
                                        CONSTRAINT `control_requirements_control_id_foreign` FOREIGN KEY (`control_id`) REFERENCES `controls` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                        CONSTRAINT `control_requirements_requirement_id_foreign` FOREIGN KEY (`requirement_id`) REFERENCES `requirements` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `control_risk_project_type_risk_template` (
                                                           `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                                           `control_id` bigint(20) unsigned NOT NULL,
                                                           `risk_project_type_risk_template_id` bigint(20) unsigned NOT NULL,
                                                           `created_at` timestamp NULL DEFAULT NULL,
                                                           `updated_at` timestamp NULL DEFAULT NULL,
                                                           PRIMARY KEY (`id`),
                                                           KEY `crptrt_ci` (`control_id`),
                                                           KEY `crptrt_rptrti` (`risk_project_type_risk_template_id`),
                                                           CONSTRAINT `crptrt_ci` FOREIGN KEY (`control_id`) REFERENCES `controls` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                                           CONSTRAINT `crptrt_rptrti` FOREIGN KEY (`risk_project_type_risk_template_id`) REFERENCES `risk_project_type_risk_templates` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `control_risks` (
                                 `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                 `control_id` bigint(20) unsigned NOT NULL,
                                 `risk_id` bigint(20) unsigned NOT NULL,
                                 PRIMARY KEY (`id`),
                                 KEY `control_risks_control_id_foreign` (`control_id`),
                                 KEY `control_risks_risk_id_foreign` (`risk_id`),
                                 CONSTRAINT `control_risks_control_id_foreign` FOREIGN KEY (`control_id`) REFERENCES `controls` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                 CONSTRAINT `control_risks_risk_id_foreign` FOREIGN KEY (`risk_id`) REFERENCES `risks` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `controls` (
                            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                            `uid` char(36) DEFAULT NULL,
                            `partner_id` bigint(20) unsigned DEFAULT NULL,
                            `partner_object_uid` char(36) DEFAULT NULL,
                            `partner_object_updated_at` timestamp NULL DEFAULT NULL,
                            `name` varchar(255) NOT NULL,
                            `description` text NOT NULL,
                            `created_at` timestamp NULL DEFAULT NULL,
                            `updated_at` timestamp NULL DEFAULT NULL,
                            `responsible_user_id` bigint(20) unsigned DEFAULT NULL,
                            `statusdescription` text DEFAULT NULL,
                            `not_applicable_at` timestamp NULL DEFAULT NULL,
                            `reviewed_at` timestamp NULL DEFAULT NULL,
                            PRIMARY KEY (`id`),
                            UNIQUE KEY `controls_uid_unique` (`uid`),
                            UNIQUE KEY `controls_partner_object_uid_unique` (`partner_object_uid`),
                            KEY `controls_responsible_user_id_foreign` (`responsible_user_id`),
                            KEY `controls_partner_id_foreign` (`partner_id`),
                            CONSTRAINT `controls_partner_id_foreign` FOREIGN KEY (`partner_id`) REFERENCES `partners` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                            CONSTRAINT `controls_responsible_user_id_foreign` FOREIGN KEY (`responsible_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `custom_properties` (
                                     `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                     `name` varchar(255) NOT NULL,
                                     `description` longtext NOT NULL,
                                     `context` varchar(255) NOT NULL,
                                     `type` varchar(20) NOT NULL,
                                     `options` longtext DEFAULT NULL,
                                     `ordinal` bigint(20) NOT NULL DEFAULT 0,
                                     `display_on_card` tinyint(1) NOT NULL DEFAULT 0,
                                     `user_editable` tinyint(1) NOT NULL DEFAULT 1,
                                     `created_at` timestamp NULL DEFAULT NULL,
                                     `updated_at` timestamp NULL DEFAULT NULL,
                                     `required` tinyint(1) NOT NULL DEFAULT 0,
                                     PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `custom_property_object` (
                                          `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                          `custom_property_id` bigint(20) unsigned NOT NULL,
                                          `object_id` bigint(20) unsigned NOT NULL,
                                          `object_type` varchar(255) NOT NULL,
                                          `value` longtext NOT NULL,
                                          `created_at` timestamp NULL DEFAULT NULL,
                                          `updated_at` timestamp NULL DEFAULT NULL,
                                          PRIMARY KEY (`id`),
                                          KEY `custom_property_object_custom_property_id_foreign` (`custom_property_id`),
                                          CONSTRAINT `custom_property_object_custom_property_id_foreign` FOREIGN KEY (`custom_property_id`) REFERENCES `custom_properties` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `customer_process` (
                                    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                    `customer_id` bigint(20) unsigned NOT NULL,
                                    `process_id` bigint(20) unsigned NOT NULL,
                                    PRIMARY KEY (`id`),
                                    KEY `customer_process_customer_id_foreign` (`customer_id`),
                                    KEY `customer_process_process_id_foreign` (`process_id`),
                                    CONSTRAINT `customer_process_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                    CONSTRAINT `customer_process_process_id_foreign` FOREIGN KEY (`process_id`) REFERENCES `processes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `customers` (
                             `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                             `uid` char(36) DEFAULT NULL,
                             `name` varchar(255) NOT NULL,
                             `legal_reg` varchar(255) DEFAULT NULL,
                             `ext_id` varchar(255) DEFAULT NULL,
                             `dpo_name` varchar(255) DEFAULT NULL,
                             `dpo_email` varchar(255) DEFAULT NULL,
                             `description` mediumtext DEFAULT NULL,
                             `responsible_user_id` bigint(20) unsigned DEFAULT NULL,
                             `created_at` timestamp NULL DEFAULT NULL,
                             `updated_at` timestamp NULL DEFAULT NULL,
                             PRIMARY KEY (`id`),
                             UNIQUE KEY `customers_uid_unique` (`uid`),
                             KEY `customers_responsible_user_id_foreign` (`responsible_user_id`),
                             CONSTRAINT `customers_responsible_user_id_foreign` FOREIGN KEY (`responsible_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `data_categories` (
                                   `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                   `name` varchar(255) NOT NULL,
                                   `description` text NOT NULL,
                                   `sensitive` tinyint(1) NOT NULL DEFAULT 0,
                                   `created_at` timestamp NULL DEFAULT NULL,
                                   `updated_at` timestamp NULL DEFAULT NULL,
                                   PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `data_category_information_type` (
                                                  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                                  `data_category_id` bigint(20) unsigned NOT NULL,
                                                  `information_type_id` bigint(20) unsigned NOT NULL,
                                                  PRIMARY KEY (`id`),
                                                  KEY `dcitdci` (`data_category_id`),
                                                  KEY `dcititi` (`information_type_id`),
                                                  CONSTRAINT `dcitdci` FOREIGN KEY (`data_category_id`) REFERENCES `data_categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                                  CONSTRAINT `dcititi` FOREIGN KEY (`information_type_id`) REFERENCES `information_types` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `department_user` (
                                   `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                   `department_id` bigint(20) unsigned NOT NULL,
                                   `user_id` bigint(20) unsigned NOT NULL,
                                   `created_at` timestamp NULL DEFAULT NULL,
                                   `updated_at` timestamp NULL DEFAULT NULL,
                                   PRIMARY KEY (`id`),
                                   KEY `department_user_department_id_foreign` (`department_id`),
                                   KEY `department_user_user_id_foreign` (`user_id`),
                                   CONSTRAINT `department_user_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                   CONSTRAINT `department_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `departments` (
                               `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                               `uid` char(36) DEFAULT NULL,
                               `name` varchar(255) NOT NULL,
                               `created_at` timestamp NULL DEFAULT NULL,
                               `updated_at` timestamp NULL DEFAULT NULL,
                               `external_provider_group_id` bigint(20) unsigned DEFAULT NULL,
                               `parent_department_id` bigint(20) unsigned DEFAULT NULL,
                               `site_id` bigint(20) unsigned DEFAULT NULL,
                               PRIMARY KEY (`id`),
                               UNIQUE KEY `departments_name_unique` (`name`),
                               UNIQUE KEY `departments_uid_unique` (`uid`),
                               KEY `departments_external_provider_group_id_foreign` (`external_provider_group_id`),
                               KEY `dpdi` (`parent_department_id`),
                               KEY `dsi_fk` (`site_id`),
                               CONSTRAINT `departments_external_provider_group_id_foreign` FOREIGN KEY (`external_provider_group_id`) REFERENCES `external_provider_groups` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                               CONSTRAINT `dpdi` FOREIGN KEY (`parent_department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                               CONSTRAINT `dsi_fk` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `diaries` (
                           `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                           `name` varchar(255) NOT NULL,
                           `description` mediumtext DEFAULT NULL,
                           `created_at` timestamp NULL DEFAULT NULL,
                           `updated_at` timestamp NULL DEFAULT NULL,
                           PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `document_versions` (
                                     `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                     `library_document_id` bigint(20) unsigned NOT NULL,
                                     `approver_id` bigint(20) unsigned DEFAULT NULL,
                                     `contents` longtext DEFAULT NULL,
                                     `major_version` int(10) unsigned NOT NULL DEFAULT 0,
                                     `minor_version` int(10) unsigned NOT NULL DEFAULT 1,
                                     `approved_at` timestamp NULL DEFAULT NULL,
                                     `finished_at` timestamp NULL DEFAULT NULL,
                                     `created_at` timestamp NULL DEFAULT NULL,
                                     `updated_at` timestamp NULL DEFAULT NULL,
                                     PRIMARY KEY (`id`),
                                     KEY `document_versions_library_document_id_foreign` (`library_document_id`),
                                     KEY `document_versions_approver_id_foreign` (`approver_id`),
                                     CONSTRAINT `document_versions_approver_id_foreign` FOREIGN KEY (`approver_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                                     CONSTRAINT `document_versions_library_document_id_foreign` FOREIGN KEY (`library_document_id`) REFERENCES `library_documents` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `external_provider_group_user` (
                                                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                                `external_provider_group_id` bigint(20) unsigned NOT NULL,
                                                `user_id` bigint(20) unsigned NOT NULL,
                                                `created_at` timestamp NULL DEFAULT NULL,
                                                `updated_at` timestamp NULL DEFAULT NULL,
                                                PRIMARY KEY (`id`),
                                                KEY `external_provider_group_user_external_provider_group_id_foreign` (`external_provider_group_id`),
                                                KEY `external_provider_group_user_user_id_foreign` (`user_id`),
                                                CONSTRAINT `external_provider_group_user_external_provider_group_id_foreign` FOREIGN KEY (`external_provider_group_id`) REFERENCES `external_provider_groups` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                                CONSTRAINT `external_provider_group_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `external_provider_groups` (
                                            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                            `external_id` varchar(255) NOT NULL,
                                            `name` varchar(255) NOT NULL,
                                            `created_at` timestamp NULL DEFAULT NULL,
                                            `updated_at` timestamp NULL DEFAULT NULL,
                                            PRIMARY KEY (`id`),
                                            UNIQUE KEY `external_provider_groups_external_id_unique` (`external_id`),
                                            UNIQUE KEY `external_provider_groups_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `failed_jobs` (
                               `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                               `uuid` varchar(255) NOT NULL,
                               `connection` text NOT NULL,
                               `queue` text NOT NULL,
                               `payload` longtext NOT NULL,
                               `exception` longtext NOT NULL,
                               `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
                               PRIMARY KEY (`id`),
                               UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `files` (
                         `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                         `object_id` bigint(20) unsigned NOT NULL,
                         `object_type` varchar(255) NOT NULL,
                         `created_by` varchar(255) NOT NULL,
                         `name` varchar(255) NOT NULL,
                         `description` text DEFAULT NULL,
                         `filename` varchar(255) NOT NULL,
                         `contenttype` varchar(255) NOT NULL,
                         `contentlength` bigint(20) unsigned NOT NULL,
                         `created_at` timestamp NULL DEFAULT NULL,
                         `updated_at` timestamp NULL DEFAULT NULL,
                         `contents` longblob DEFAULT NULL,
                         PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `findings` (
                            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                            `uid` char(36) DEFAULT NULL,
                            `name` varchar(255) NOT NULL,
                            `description` text NOT NULL,
                            `department_id` bigint(20) unsigned NOT NULL,
                            `finished_at` datetime DEFAULT NULL,
                            `nonconformity` tinyint(1) NOT NULL DEFAULT 0,
                            `consequence` text DEFAULT NULL,
                            `rootcause` text DEFAULT NULL,
                            `immediateaction` text DEFAULT NULL,
                            `preventativeaction` text DEFAULT NULL,
                            `compliance_evaluation_requirement_finding_id` bigint(20) unsigned DEFAULT NULL,
                            `created_at` timestamp NULL DEFAULT NULL,
                            `updated_at` timestamp NULL DEFAULT NULL,
                            `context_type` varchar(255) DEFAULT NULL,
                            `context_id` bigint(20) unsigned DEFAULT NULL,
                            `created_by` bigint(20) unsigned DEFAULT NULL,
                            `estimated_cost` bigint(20) unsigned DEFAULT NULL,
                            `distribution_analysis` longtext DEFAULT NULL,
                            PRIMARY KEY (`id`),
                            UNIQUE KEY `findings_uid_unique` (`uid`),
                            KEY `findings_department_id_foreign` (`department_id`),
                            KEY `findings_compliance_evaluation_requirement_finding_id_foreign` (`compliance_evaluation_requirement_finding_id`),
                            KEY `findings_created_by_foreign` (`created_by`),
                            CONSTRAINT `findings_compliance_evaluation_requirement_finding_id_foreign` FOREIGN KEY (`compliance_evaluation_requirement_finding_id`) REFERENCES `compliance_evaluation_requirement_findings` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                            CONSTRAINT `findings_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                            CONSTRAINT `findings_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `ignored_risks` (
                                 `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                 `risk_id` bigint(20) unsigned NOT NULL,
                                 `name` varchar(255) NOT NULL,
                                 `scenariodescription` longtext NOT NULL,
                                 `partner_id` bigint(20) unsigned NOT NULL,
                                 `partner_object_uid` char(36) NOT NULL,
                                 `context_type` varchar(255) NOT NULL,
                                 `context_id` bigint(20) unsigned DEFAULT NULL,
                                 `created_by` varchar(255) DEFAULT NULL,
                                 `created_at` timestamp NULL DEFAULT NULL,
                                 `updated_at` timestamp NULL DEFAULT NULL,
                                 `partner_informed_at` timestamp NULL DEFAULT NULL,
                                 PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `incident_logs` (
                                 `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                 `uid` char(36) DEFAULT NULL,
                                 `incident_id` bigint(20) unsigned NOT NULL,
                                 `start_at` datetime NOT NULL DEFAULT current_timestamp(),
                                 `description` text NOT NULL,
                                 `created_at` timestamp NULL DEFAULT NULL,
                                 `updated_at` timestamp NULL DEFAULT NULL,
                                 PRIMARY KEY (`id`),
                                 UNIQUE KEY `incident_logs_uid_unique` (`uid`),
                                 KEY `incident_logs_incident_id_foreign` (`incident_id`),
                                 CONSTRAINT `incident_logs_incident_id_foreign` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `incidents` (
                             `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                             `uid` char(36) DEFAULT NULL,
                             `name` varchar(255) NOT NULL,
                             `started_at` datetime NOT NULL DEFAULT current_timestamp(),
                             `finished_at` datetime DEFAULT NULL,
                             `eventdescription` text NOT NULL,
                             `participants` text DEFAULT NULL,
                             `retrospective` text DEFAULT NULL,
                             `responsible_user_id` bigint(20) unsigned DEFAULT NULL,
                             `created_at` timestamp NULL DEFAULT NULL,
                             `updated_at` timestamp NULL DEFAULT NULL,
                             PRIMARY KEY (`id`),
                             UNIQUE KEY `incidents_uid_unique` (`uid`),
                             KEY `incidents_responsible_user_id_foreign` (`responsible_user_id`),
                             CONSTRAINT `incidents_responsible_user_id_foreign` FOREIGN KEY (`responsible_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `information_type_process_activity` (
                                                     `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                                     `information_type_id` bigint(20) unsigned NOT NULL,
                                                     `process_activity_id` bigint(20) unsigned NOT NULL,
                                                     PRIMARY KEY (`id`),
                                                     KEY `information_type_process_activity_information_type_id_foreign` (`information_type_id`),
                                                     KEY `information_type_process_activity_process_activity_id_foreign` (`process_activity_id`),
                                                     CONSTRAINT `information_type_process_activity_information_type_id_foreign` FOREIGN KEY (`information_type_id`) REFERENCES `information_types` (`id`) ON UPDATE CASCADE,
                                                     CONSTRAINT `information_type_process_activity_process_activity_id_foreign` FOREIGN KEY (`process_activity_id`) REFERENCES `process_activities` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `information_type_recipient_category` (
                                                       `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                                       `recipient_category_id` bigint(20) unsigned NOT NULL,
                                                       `information_type_id` bigint(20) unsigned NOT NULL,
                                                       PRIMARY KEY (`id`),
                                                       KEY `itrciti` (`information_type_id`),
                                                       KEY `itrcrci` (`recipient_category_id`),
                                                       CONSTRAINT `itrciti` FOREIGN KEY (`information_type_id`) REFERENCES `information_types` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                                       CONSTRAINT `itrcrci` FOREIGN KEY (`recipient_category_id`) REFERENCES `recipient_categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `information_type_subject_category` (
                                                     `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                                     `information_type_id` bigint(20) unsigned NOT NULL,
                                                     `subject_category_id` bigint(20) unsigned NOT NULL,
                                                     PRIMARY KEY (`id`),
                                                     KEY `itsciti` (`information_type_id`),
                                                     KEY `itscsci` (`subject_category_id`),
                                                     CONSTRAINT `itsciti` FOREIGN KEY (`information_type_id`) REFERENCES `information_types` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                                     CONSTRAINT `itscsci` FOREIGN KEY (`subject_category_id`) REFERENCES `subject_categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `information_types` (
                                     `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                     `uid` char(36) DEFAULT NULL,
                                     `name` varchar(255) NOT NULL,
                                     `description` longtext DEFAULT NULL,
                                     `responsible_user_id` bigint(20) unsigned DEFAULT NULL,
                                     `created_at` timestamp NULL DEFAULT NULL,
                                     `updated_at` timestamp NULL DEFAULT NULL,
                                     `confidentiality_class_id` bigint(20) unsigned DEFAULT NULL,
                                     `integrity_class_id` bigint(20) unsigned DEFAULT NULL,
                                     `availability_class_id` bigint(20) unsigned DEFAULT NULL,
                                     `retention` int(10) unsigned DEFAULT NULL,
                                     `piidescription` longtext DEFAULT NULL,
                                     `confidentiality_ground_id` bigint(20) unsigned DEFAULT NULL,
                                     `diary_id` bigint(20) unsigned DEFAULT NULL,
                                     `archivingdescription` longtext DEFAULT NULL,
                                     `archiveshippingtime` int(10) unsigned DEFAULT NULL,
                                     `archivemedia` varchar(255) DEFAULT NULL,
                                     `sortinginformation` varchar(255) DEFAULT NULL,
                                     PRIMARY KEY (`id`),
                                     UNIQUE KEY `information_types_name_unique` (`name`),
                                     UNIQUE KEY `information_types_uid_unique` (`uid`),
                                     KEY `information_types_responsible_user_id_foreign` (`responsible_user_id`),
                                     KEY `information_types_confidentiality_class_id_foreign` (`confidentiality_class_id`),
                                     KEY `information_types_integrity_class_id_foreign` (`integrity_class_id`),
                                     KEY `information_types_availability_class_id_foreign` (`availability_class_id`),
                                     KEY `itcgi_fk` (`confidentiality_ground_id`),
                                     KEY `itdi_fk` (`diary_id`),
                                     CONSTRAINT `information_types_availability_class_id_foreign` FOREIGN KEY (`availability_class_id`) REFERENCES `availability_classes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                                     CONSTRAINT `information_types_confidentiality_class_id_foreign` FOREIGN KEY (`confidentiality_class_id`) REFERENCES `confidentiality_classes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                                     CONSTRAINT `information_types_integrity_class_id_foreign` FOREIGN KEY (`integrity_class_id`) REFERENCES `integrity_classes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                                     CONSTRAINT `information_types_responsible_user_id_foreign` FOREIGN KEY (`responsible_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                                     CONSTRAINT `itcgi_fk` FOREIGN KEY (`confidentiality_ground_id`) REFERENCES `confidentiality_grounds` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                                     CONSTRAINT `itdi_fk` FOREIGN KEY (`diary_id`) REFERENCES `diaries` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `integrity_classes` (
                                     `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                     `name` varchar(255) NOT NULL,
                                     `description` text NOT NULL,
                                     `ordinal` int(10) unsigned NOT NULL DEFAULT 0,
                                     `created_at` timestamp NULL DEFAULT NULL,
                                     `updated_at` timestamp NULL DEFAULT NULL,
                                     PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `legal_bases` (
                               `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                               `name` varchar(255) NOT NULL,
                               `description` text NOT NULL,
                               `sensitive` tinyint(1) NOT NULL DEFAULT 0,
                               `consent` tinyint(1) NOT NULL DEFAULT 0,
                               `created_at` timestamp NULL DEFAULT NULL,
                               `updated_at` timestamp NULL DEFAULT NULL,
                               PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `legal_basis_process` (
                                       `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                       `legal_basis_id` bigint(20) unsigned NOT NULL,
                                       `process_id` bigint(20) unsigned NOT NULL,
                                       PRIMARY KEY (`id`),
                                       KEY `legal_basis_process_legal_basis_id_foreign` (`legal_basis_id`),
                                       KEY `legal_basis_process_process_id_foreign` (`process_id`),
                                       CONSTRAINT `legal_basis_process_legal_basis_id_foreign` FOREIGN KEY (`legal_basis_id`) REFERENCES `legal_bases` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                       CONSTRAINT `legal_basis_process_process_id_foreign` FOREIGN KEY (`process_id`) REFERENCES `processes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `library_document_processes` (
                                              `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                              `process_id` bigint(20) unsigned NOT NULL,
                                              `library_document_id` bigint(20) unsigned NOT NULL,
                                              `created_at` timestamp NULL DEFAULT NULL,
                                              `updated_at` timestamp NULL DEFAULT NULL,
                                              PRIMARY KEY (`id`),
                                              KEY `library_document_processes_process_id_foreign` (`process_id`),
                                              KEY `library_document_processes_library_document_id_foreign` (`library_document_id`),
                                              CONSTRAINT `library_document_processes_library_document_id_foreign` FOREIGN KEY (`library_document_id`) REFERENCES `library_documents` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                              CONSTRAINT `library_document_processes_process_id_foreign` FOREIGN KEY (`process_id`) REFERENCES `processes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `library_documents` (
                                     `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                     `uid` char(36) DEFAULT NULL,
                                     `partner_id` bigint(20) unsigned DEFAULT NULL,
                                     `partner_object_uid` char(36) DEFAULT NULL,
                                     `partner_object_updated_at` timestamp NULL DEFAULT NULL,
                                     `name` varchar(255) NOT NULL,
                                     `filename` varchar(255) NOT NULL,
                                     `description` text DEFAULT NULL,
                                     `contenttype` varchar(255) NOT NULL,
                                     `contentlength` bigint(20) unsigned NOT NULL,
                                     `created_at` timestamp NULL DEFAULT NULL,
                                     `updated_at` timestamp NULL DEFAULT NULL,
                                     `filecontent` longblob DEFAULT NULL,
                                     `responsible_user_id` bigint(20) unsigned DEFAULT NULL,
                                     PRIMARY KEY (`id`),
                                     UNIQUE KEY `library_documents_uid_unique` (`uid`),
                                     UNIQUE KEY `library_documents_partner_object_uid_unique` (`partner_object_uid`),
                                     KEY `library_documents_partner_id_foreign` (`partner_id`),
                                     KEY `ld_rui` (`responsible_user_id`),
                                     CONSTRAINT `ld_rui` FOREIGN KEY (`responsible_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                                     CONSTRAINT `library_documents_partner_id_foreign` FOREIGN KEY (`partner_id`) REFERENCES `partners` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `object_histories` (
                                    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                    `uid` char(36) DEFAULT NULL,
                                    `action` varchar(1) NOT NULL,
                                    `modified` longtext DEFAULT NULL CHECK (json_valid(`modified`)),
                                    `created_by` varchar(255) DEFAULT NULL,
                                    `object_id` bigint(20) unsigned NOT NULL,
                                    `object_type` varchar(255) NOT NULL,
                                    `created_at` timestamp NULL DEFAULT NULL,
                                    `updated_at` timestamp NULL DEFAULT NULL,
                                    PRIMARY KEY (`id`),
                                    UNIQUE KEY `object_histories_uid_unique` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `object_messages` (
                                   `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                   `uid` char(36) DEFAULT NULL,
                                   `comment` longtext NOT NULL,
                                   `created_by` varchar(255) NOT NULL,
                                   `object_id` bigint(20) unsigned NOT NULL,
                                   `object_type` varchar(255) NOT NULL,
                                   `created_at` timestamp NULL DEFAULT NULL,
                                   `updated_at` timestamp NULL DEFAULT NULL,
                                   PRIMARY KEY (`id`),
                                   UNIQUE KEY `object_messages_uid_unique` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `object_properties` (
                                     `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                     `object_properties_id` bigint(20) unsigned NOT NULL,
                                     `property_id` bigint(20) unsigned NOT NULL,
                                     `object_properties_type` varchar(255) NOT NULL,
                                     `value` int(11) NOT NULL DEFAULT 0,
                                     `created_at` timestamp NULL DEFAULT NULL,
                                     `updated_at` timestamp NULL DEFAULT NULL,
                                     PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `object_tags` (
                               `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                               `tag_id` bigint(20) unsigned NOT NULL,
                               `object_tags_id` bigint(20) unsigned NOT NULL,
                               `object_tags_type` varchar(255) NOT NULL,
                               PRIMARY KEY (`id`),
                               KEY `object_tags_tag_id_foreign` (`tag_id`),
                               CONSTRAINT `object_tags_tag_id_foreign` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `objective_process_performance_metrics` (
                                                         `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                                         `objective_id` bigint(20) unsigned NOT NULL,
                                                         `process_performance_metric_id` bigint(20) unsigned NOT NULL,
                                                         `objective_target_value` bigint(20) NOT NULL,
                                                         `objective_acceptable_value` bigint(20) NOT NULL,
                                                         `precision` int(10) unsigned NOT NULL,
                                                         `created_at` timestamp NULL DEFAULT NULL,
                                                         `updated_at` timestamp NULL DEFAULT NULL,
                                                         PRIMARY KEY (`id`),
                                                         KEY `oppmoi` (`objective_id`),
                                                         KEY `oppmppmi` (`process_performance_metric_id`),
                                                         CONSTRAINT `oppmoi` FOREIGN KEY (`objective_id`) REFERENCES `objectives` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                                         CONSTRAINT `oppmppmi` FOREIGN KEY (`process_performance_metric_id`) REFERENCES `process_performance_metrics` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `objective_process_sustainability_aspect` (
                                                           `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                                           `process_sustainability_aspect_id` bigint(20) unsigned NOT NULL,
                                                           `objective_id` bigint(20) unsigned NOT NULL,
                                                           `created_at` timestamp NULL DEFAULT NULL,
                                                           `updated_at` timestamp NULL DEFAULT NULL,
                                                           PRIMARY KEY (`id`),
                                                           KEY `opsa_psai` (`process_sustainability_aspect_id`),
                                                           KEY `opsa_oi` (`objective_id`),
                                                           CONSTRAINT `opsa_oi` FOREIGN KEY (`objective_id`) REFERENCES `objectives` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                                           CONSTRAINT `opsa_psai` FOREIGN KEY (`process_sustainability_aspect_id`) REFERENCES `process_sustainability_aspects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `objectives` (
                              `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                              `name` varchar(255) NOT NULL,
                              `description` longtext DEFAULT NULL,
                              `responsible_user_id` bigint(20) unsigned DEFAULT NULL,
                              `due` date DEFAULT NULL,
                              `created_at` timestamp NULL DEFAULT NULL,
                              `updated_at` timestamp NULL DEFAULT NULL,
                              `archived_at` timestamp NULL DEFAULT NULL,
                              `action_plan` longtext DEFAULT NULL,
                              `department_id` bigint(20) unsigned DEFAULT NULL,
                              PRIMARY KEY (`id`),
                              KEY `orui` (`responsible_user_id`),
                              KEY `o_department_fk` (`department_id`),
                              CONSTRAINT `o_department_fk` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                              CONSTRAINT `orui` FOREIGN KEY (`responsible_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `partners` (
                            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                            `uid` char(36) DEFAULT NULL,
                            `name` varchar(50) NOT NULL,
                            `description` text DEFAULT NULL,
                            `url` varchar(255) NOT NULL,
                            `authtoken` varchar(64) DEFAULT NULL,
                            `lastseen` datetime DEFAULT NULL,
                            `created_at` timestamp NULL DEFAULT NULL,
                            `updated_at` timestamp NULL DEFAULT NULL,
                            `company_risk_department_id` bigint(20) unsigned DEFAULT NULL,
                            `department_risk_department_id` bigint(20) unsigned DEFAULT NULL,
                            `process_risk_department_id` bigint(20) unsigned DEFAULT NULL,
                            `information_type_risk_department_id` bigint(20) unsigned DEFAULT NULL,
                            `asset_risk_department_id` bigint(20) unsigned DEFAULT NULL,
                            `supplier_risk_department_id` bigint(20) unsigned DEFAULT NULL,
                            `fallback_risk_department_id` bigint(20) unsigned DEFAULT NULL,
                            `customer_risk_department_id` bigint(20) unsigned DEFAULT NULL,
                            `site_risk_department_id` bigint(20) unsigned DEFAULT NULL,
                            PRIMARY KEY (`id`),
                            UNIQUE KEY `partners_name_unique` (`name`),
                            UNIQUE KEY `partners_url_unique` (`url`),
                            UNIQUE KEY `partners_uid_unique` (`uid`),
                            KEY `pcrdi` (`company_risk_department_id`),
                            KEY `pdrdi` (`department_risk_department_id`),
                            KEY `pprdi` (`process_risk_department_id`),
                            KEY `pitrdi` (`information_type_risk_department_id`),
                            KEY `pardi` (`asset_risk_department_id`),
                            KEY `psrdi` (`supplier_risk_department_id`),
                            KEY `pfrdi` (`fallback_risk_department_id`),
                            KEY `pcustrdi` (`customer_risk_department_id`),
                            KEY `pcsrdi` (`site_risk_department_id`),
                            CONSTRAINT `pardi` FOREIGN KEY (`asset_risk_department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                            CONSTRAINT `pcrdi` FOREIGN KEY (`company_risk_department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                            CONSTRAINT `pcsrdi` FOREIGN KEY (`site_risk_department_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                            CONSTRAINT `pcustrdi` FOREIGN KEY (`customer_risk_department_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                            CONSTRAINT `pdrdi` FOREIGN KEY (`department_risk_department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                            CONSTRAINT `pfrdi` FOREIGN KEY (`fallback_risk_department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                            CONSTRAINT `pitrdi` FOREIGN KEY (`information_type_risk_department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                            CONSTRAINT `pprdi` FOREIGN KEY (`process_risk_department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                            CONSTRAINT `psrdi` FOREIGN KEY (`supplier_risk_department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `password_resets` (
                                   `email` varchar(255) NOT NULL,
                                   `token` varchar(255) NOT NULL,
                                   `created_at` timestamp NULL DEFAULT NULL,
                                   KEY `password_resets_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `pending_activities` (
                                      `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                      `description` text DEFAULT NULL,
                                      `dependant_activity_id` bigint(20) unsigned DEFAULT NULL,
                                      `activity_flow_id` bigint(20) unsigned NOT NULL,
                                      `activity_flow_template_item_id` bigint(20) unsigned NOT NULL,
                                      `responsible_user_id` bigint(20) unsigned NOT NULL,
                                      `created_at` timestamp NULL DEFAULT NULL,
                                      `updated_at` timestamp NULL DEFAULT NULL,
                                      `dependant_pending_activity_id` bigint(20) unsigned DEFAULT NULL,
                                      PRIMARY KEY (`id`),
                                      KEY `pending_activities_dependant_activity_id_foreign` (`dependant_activity_id`),
                                      KEY `pending_activities_activity_flow_id_foreign` (`activity_flow_id`),
                                      KEY `pending_activities_activity_flow_template_item_id_foreign` (`activity_flow_template_item_id`),
                                      KEY `pending_activities_responsible_user_id_foreign` (`responsible_user_id`),
                                      KEY `pa_dpai_fk` (`dependant_pending_activity_id`),
                                      CONSTRAINT `pa_dpai_fk` FOREIGN KEY (`dependant_pending_activity_id`) REFERENCES `pending_activities` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                      CONSTRAINT `pending_activities_activity_flow_id_foreign` FOREIGN KEY (`activity_flow_id`) REFERENCES `activity_flows` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                      CONSTRAINT `pending_activities_activity_flow_template_item_id_foreign` FOREIGN KEY (`activity_flow_template_item_id`) REFERENCES `activity_flow_template_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                      CONSTRAINT `pending_activities_dependant_activity_id_foreign` FOREIGN KEY (`dependant_activity_id`) REFERENCES `activities` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                      CONSTRAINT `pending_activities_responsible_user_id_foreign` FOREIGN KEY (`responsible_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `personal_access_tokens` (
                                          `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                          `tokenable_type` varchar(255) NOT NULL,
                                          `tokenable_id` bigint(20) unsigned NOT NULL,
                                          `name` varchar(255) NOT NULL,
                                          `token` varchar(64) NOT NULL,
                                          `abilities` text DEFAULT NULL,
                                          `last_used_at` timestamp NULL DEFAULT NULL,
                                          `expires_at` timestamp NULL DEFAULT NULL,
                                          `created_at` timestamp NULL DEFAULT NULL,
                                          `updated_at` timestamp NULL DEFAULT NULL,
                                          PRIMARY KEY (`id`),
                                          UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
                                          KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `probability_levels` (
                                      `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                      `name` varchar(255) NOT NULL,
                                      `description` text NOT NULL,
                                      `ordinal` int(10) unsigned NOT NULL DEFAULT 0,
                                      `created_at` timestamp NULL DEFAULT NULL,
                                      `updated_at` timestamp NULL DEFAULT NULL,
                                      PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `process_activities` (
                                      `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                      `uid` char(36) DEFAULT NULL,
                                      `name` varchar(255) NOT NULL,
                                      `description` longtext DEFAULT NULL,
                                      `bpmnId` varchar(255) NOT NULL,
                                      `ordinal` bigint(20) NOT NULL DEFAULT 0,
                                      `process_id` bigint(20) unsigned NOT NULL,
                                      `responsible_role_id` bigint(20) unsigned DEFAULT NULL,
                                      `accountable_role_id` bigint(20) unsigned DEFAULT NULL,
                                      `created_at` timestamp NULL DEFAULT NULL,
                                      `updated_at` timestamp NULL DEFAULT NULL,
                                      PRIMARY KEY (`id`),
                                      UNIQUE KEY `process_activities_bpmnid_unique` (`bpmnId`),
                                      UNIQUE KEY `process_activities_uid_unique` (`uid`),
                                      KEY `process_activities_process_id_foreign` (`process_id`),
                                      KEY `process_activities_responsible_role_id_foreign` (`responsible_role_id`),
                                      KEY `process_activities_accountable_role_id_foreign` (`accountable_role_id`),
                                      CONSTRAINT `process_activities_accountable_role_id_foreign` FOREIGN KEY (`accountable_role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                                      CONSTRAINT `process_activities_process_id_foreign` FOREIGN KEY (`process_id`) REFERENCES `processes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                      CONSTRAINT `process_activities_responsible_role_id_foreign` FOREIGN KEY (`responsible_role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `process_activity_supplier` (
                                             `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                             `supplier_id` bigint(20) unsigned NOT NULL,
                                             `process_activity_id` bigint(20) unsigned NOT NULL,
                                             PRIMARY KEY (`id`),
                                             KEY `process_activity_supplier_supplier_id_foreign` (`supplier_id`),
                                             KEY `process_activity_supplier_process_activity_id_foreign` (`process_activity_id`),
                                             CONSTRAINT `process_activity_supplier_process_activity_id_foreign` FOREIGN KEY (`process_activity_id`) REFERENCES `process_activities` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                             CONSTRAINT `process_activity_supplier_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `process_hrefs` (
                                 `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                 `process_id` bigint(20) unsigned NOT NULL,
                                 `name` varchar(255) NOT NULL,
                                 `description` text DEFAULT NULL,
                                 `url` varchar(255) NOT NULL,
                                 `blank` tinyint(1) NOT NULL DEFAULT 1,
                                 `ordinal` int(10) unsigned NOT NULL DEFAULT 0,
                                 `created_at` timestamp NULL DEFAULT NULL,
                                 `updated_at` timestamp NULL DEFAULT NULL,
                                 PRIMARY KEY (`id`),
                                 KEY `process_hrefs_process_id_foreign` (`process_id`),
                                 CONSTRAINT `process_hrefs_process_id_foreign` FOREIGN KEY (`process_id`) REFERENCES `processes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `process_links` (
                                 `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                 `linked_process_id` bigint(20) unsigned NOT NULL,
                                 `process_id` bigint(20) unsigned NOT NULL,
                                 PRIMARY KEY (`id`),
                                 KEY `process_links_process_id_foreign` (`process_id`),
                                 KEY `process_links_linked_process_id_foreign` (`linked_process_id`),
                                 CONSTRAINT `process_links_linked_process_id_foreign` FOREIGN KEY (`linked_process_id`) REFERENCES `processes` (`id`) ON UPDATE CASCADE,
                                 CONSTRAINT `process_links_process_id_foreign` FOREIGN KEY (`process_id`) REFERENCES `processes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `process_performance_metric_process_sustainability_aspect` (
                                                                            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                                                            `process_sustainability_aspect_id` bigint(20) unsigned NOT NULL,
                                                                            `process_performance_metric_id` bigint(20) unsigned NOT NULL,
                                                                            `created_at` timestamp NULL DEFAULT NULL,
                                                                            `updated_at` timestamp NULL DEFAULT NULL,
                                                                            PRIMARY KEY (`id`),
                                                                            KEY `ppmpsa_psai` (`process_sustainability_aspect_id`),
                                                                            KEY `ppmpsa_ppmi` (`process_performance_metric_id`),
                                                                            CONSTRAINT `ppmpsa_ppmi` FOREIGN KEY (`process_performance_metric_id`) REFERENCES `process_performance_metrics` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                                                            CONSTRAINT `ppmpsa_psai` FOREIGN KEY (`process_sustainability_aspect_id`) REFERENCES `process_sustainability_aspects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `process_performance_metric_reports` (
                                                      `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                                      `process_performance_metric_id` bigint(20) unsigned NOT NULL,
                                                      `reported_by_id` bigint(20) unsigned DEFAULT NULL,
                                                      `value` bigint(20) DEFAULT NULL,
                                                      `reportedprecision` int(10) unsigned DEFAULT NULL,
                                                      `reporting_date_at` date NOT NULL,
                                                      `comment` longtext DEFAULT NULL,
                                                      `created_at` timestamp NULL DEFAULT NULL,
                                                      `updated_at` timestamp NULL DEFAULT NULL,
                                                      PRIMARY KEY (`id`),
                                                      KEY `ppmrppmi` (`process_performance_metric_id`),
                                                      KEY `ppmrrbi` (`reported_by_id`),
                                                      CONSTRAINT `ppmrppmi` FOREIGN KEY (`process_performance_metric_id`) REFERENCES `process_performance_metrics` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                                      CONSTRAINT `ppmrrbi` FOREIGN KEY (`reported_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `process_performance_metrics` (
                                               `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                               `name` varchar(255) NOT NULL,
                                               `description` longtext DEFAULT NULL,
                                               `responsible_user_id` bigint(20) unsigned DEFAULT NULL,
                                               `quantitative` tinyint(1) NOT NULL DEFAULT 0,
                                               `biggerisbetter` tinyint(1) NOT NULL DEFAULT 1,
                                               `unit` varchar(30) DEFAULT NULL,
                                               `increment` varchar(255) DEFAULT NULL,
                                               `minvalue` bigint(20) DEFAULT NULL,
                                               `maxvalue` bigint(20) DEFAULT NULL,
                                               `precision` int(10) unsigned DEFAULT NULL,
                                               `created_at` timestamp NULL DEFAULT NULL,
                                               `updated_at` timestamp NULL DEFAULT NULL,
                                               `postprocessing` longtext DEFAULT NULL,
                                               `alarm_threshold` bigint(20) DEFAULT NULL,
                                               PRIMARY KEY (`id`),
                                               KEY `process_performance_metrics_responsible_user_id_foreign` (`responsible_user_id`),
                                               CONSTRAINT `process_performance_metrics_responsible_user_id_foreign` FOREIGN KEY (`responsible_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `process_process_performance_metric` (
                                                      `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                                      `process_performance_metric_id` bigint(20) unsigned NOT NULL,
                                                      `process_id` bigint(20) unsigned NOT NULL,
                                                      `created_at` timestamp NULL DEFAULT NULL,
                                                      `updated_at` timestamp NULL DEFAULT NULL,
                                                      PRIMARY KEY (`id`),
                                                      KEY `pppmrppmi` (`process_performance_metric_id`),
                                                      KEY `pppmpi` (`process_id`),
                                                      CONSTRAINT `pppmpi` FOREIGN KEY (`process_id`) REFERENCES `processes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                                      CONSTRAINT `pppmrppmi` FOREIGN KEY (`process_performance_metric_id`) REFERENCES `process_performance_metrics` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `process_sustainability_aspect_sustainability_metric` (
                                                                       `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                                                       `process_sustainability_aspect_id` bigint(20) unsigned NOT NULL,
                                                                       `sustainability_metric_level_id` bigint(20) unsigned NOT NULL,
                                                                       `sustainability_metric_id` bigint(20) unsigned NOT NULL,
                                                                       `created_at` timestamp NULL DEFAULT NULL,
                                                                       `updated_at` timestamp NULL DEFAULT NULL,
                                                                       PRIMARY KEY (`id`),
                                                                       KEY `psasm_psai` (`process_sustainability_aspect_id`),
                                                                       KEY `psasm_smli` (`sustainability_metric_level_id`),
                                                                       KEY `psasm_smi` (`sustainability_metric_id`),
                                                                       CONSTRAINT `psasm_psai` FOREIGN KEY (`process_sustainability_aspect_id`) REFERENCES `process_sustainability_aspects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                                                       CONSTRAINT `psasm_smi` FOREIGN KEY (`sustainability_metric_id`) REFERENCES `sustainability_metrics` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                                                       CONSTRAINT `psasm_smli` FOREIGN KEY (`sustainability_metric_level_id`) REFERENCES `sustainability_metric_levels` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `process_sustainability_aspects` (
                                                  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                                  `name` varchar(255) NOT NULL,
                                                  `description` mediumtext DEFAULT NULL,
                                                  `impact_description` mediumtext DEFAULT NULL,
                                                  `monitoring_description` mediumtext DEFAULT NULL,
                                                  `governance_description` mediumtext DEFAULT NULL,
                                                  `sustainability_aspect_id` bigint(20) unsigned NOT NULL,
                                                  `process_id` bigint(20) unsigned NOT NULL,
                                                  `created_at` timestamp NULL DEFAULT NULL,
                                                  `updated_at` timestamp NULL DEFAULT NULL,
                                                  PRIMARY KEY (`id`),
                                                  KEY `psa_sai` (`sustainability_aspect_id`),
                                                  KEY `psa_pi` (`process_id`),
                                                  CONSTRAINT `psa_pi` FOREIGN KEY (`process_id`) REFERENCES `processes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                                  CONSTRAINT `psa_sai` FOREIGN KEY (`sustainability_aspect_id`) REFERENCES `sustainability_aspects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `processes` (
                             `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                             `uid` char(36) DEFAULT NULL,
                             `name` varchar(255) NOT NULL,
                             `description` longtext DEFAULT NULL,
                             `bpmn` longtext DEFAULT NULL,
                             `publishedbpmn` longtext DEFAULT NULL,
                             `svg` longtext DEFAULT NULL,
                             `department_id` bigint(20) unsigned NOT NULL,
                             `responsible_user_id` bigint(20) unsigned DEFAULT NULL,
                             `isstartprocess` tinyint(1) NOT NULL DEFAULT 0,
                             `created_at` timestamp NULL DEFAULT NULL,
                             `updated_at` timestamp NULL DEFAULT NULL,
                             `legalbasisdescription` longtext DEFAULT NULL,
                             `thirdcountrytransferdescription` longtext DEFAULT NULL,
                             `thirdcountrytransferprotectiondescription` longtext DEFAULT NULL,
                             `securitymeasuredescription` longtext DEFAULT NULL,
                             `dataprocessor` tinyint(1) NOT NULL DEFAULT 0,
                             `data_processor_processing_activities` mediumtext DEFAULT NULL,
                             PRIMARY KEY (`id`),
                             UNIQUE KEY `processes_name_unique` (`name`),
                             UNIQUE KEY `processes_uid_unique` (`uid`),
                             KEY `processes_department_foreign` (`department_id`),
                             KEY `processes_responsible_user_id_foreign` (`responsible_user_id`),
                             CONSTRAINT `processes_department_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON UPDATE CASCADE,
                             CONSTRAINT `processes_responsible_user_id_foreign` FOREIGN KEY (`responsible_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `properties` (
                              `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                              `uid` char(36) DEFAULT NULL,
                              `property_tab_id` bigint(20) unsigned NOT NULL,
                              `partner_object_uid` char(36) DEFAULT NULL,
                              `partner_object_updated_at` timestamp NULL DEFAULT NULL,
                              `flagname` varchar(255) NOT NULL,
                              `name` varchar(255) NOT NULL,
                              `description` longtext DEFAULT NULL,
                              `created_at` timestamp NULL DEFAULT NULL,
                              `updated_at` timestamp NULL DEFAULT NULL,
                              `defaultvalue` tinyint(1) DEFAULT NULL,
                              PRIMARY KEY (`id`),
                              UNIQUE KEY `properties_uid_unique` (`uid`),
                              UNIQUE KEY `properties_partner_object_uid_unique` (`partner_object_uid`),
                              KEY `properties_property_tab_id_foreign` (`property_tab_id`),
                              CONSTRAINT `properties_property_tab_id_foreign` FOREIGN KEY (`property_tab_id`) REFERENCES `property_tabs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `property_tabs` (
                                 `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                 `uid` char(36) DEFAULT NULL,
                                 `partner_id` bigint(20) unsigned DEFAULT NULL,
                                 `partner_object_uid` char(36) DEFAULT NULL,
                                 `partner_object_updated_at` timestamp NULL DEFAULT NULL,
                                 `name` varchar(50) NOT NULL,
                                 `context` varchar(255) NOT NULL,
                                 `description` text DEFAULT NULL,
                                 `created_at` timestamp NULL DEFAULT NULL,
                                 `updated_at` timestamp NULL DEFAULT NULL,
                                 PRIMARY KEY (`id`),
                                 UNIQUE KEY `property_tabs_name_unique` (`name`),
                                 UNIQUE KEY `property_tabs_uid_unique` (`uid`),
                                 UNIQUE KEY `property_tabs_partner_object_uid_unique` (`partner_object_uid`),
                                 KEY `property_tabs_partner_id_foreign` (`partner_id`),
                                 CONSTRAINT `property_tabs_partner_id_foreign` FOREIGN KEY (`partner_id`) REFERENCES `partners` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `qualification_role` (
                                      `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                      `qualification_id` bigint(20) unsigned NOT NULL,
                                      `role_id` bigint(20) unsigned NOT NULL,
                                      `mandatory` tinyint(1) NOT NULL DEFAULT 0,
                                      `created_at` timestamp NULL DEFAULT NULL,
                                      `updated_at` timestamp NULL DEFAULT NULL,
                                      PRIMARY KEY (`id`),
                                      KEY `qualification_role_qualification_id_foreign` (`qualification_id`),
                                      KEY `qualification_role_role_id_foreign` (`role_id`),
                                      CONSTRAINT `qualification_role_qualification_id_foreign` FOREIGN KEY (`qualification_id`) REFERENCES `qualifications` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                      CONSTRAINT `qualification_role_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `qualification_user` (
                                      `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                      `qualification_id` bigint(20) unsigned NOT NULL,
                                      `user_id` bigint(20) unsigned NOT NULL,
                                      `note` longtext DEFAULT NULL,
                                      `planned_at` date DEFAULT NULL,
                                      `finished_at` date DEFAULT NULL,
                                      `expires_at` date DEFAULT NULL,
                                      `filename` varchar(255) DEFAULT NULL,
                                      `contenttype` varchar(255) DEFAULT NULL,
                                      `created_at` timestamp NULL DEFAULT NULL,
                                      `updated_at` timestamp NULL DEFAULT NULL,
                                      `file` longblob DEFAULT NULL,
                                      PRIMARY KEY (`id`),
                                      KEY `qualification_user_qualification_id_foreign` (`qualification_id`),
                                      KEY `qualification_user_user_id_foreign` (`user_id`),
                                      CONSTRAINT `qualification_user_qualification_id_foreign` FOREIGN KEY (`qualification_id`) REFERENCES `qualifications` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                      CONSTRAINT `qualification_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `qualifications` (
                                  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                  `name` varchar(255) NOT NULL,
                                  `description` longtext DEFAULT NULL,
                                  `expires` tinyint(1) NOT NULL DEFAULT 0,
                                  `created_at` timestamp NULL DEFAULT NULL,
                                  `updated_at` timestamp NULL DEFAULT NULL,
                                  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `recipient_categories` (
                                        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                        `name` varchar(255) NOT NULL,
                                        `description` text NOT NULL,
                                        `created_at` timestamp NULL DEFAULT NULL,
                                        `updated_at` timestamp NULL DEFAULT NULL,
                                        PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `requirement_sources` (
                                       `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                       `uid` char(36) DEFAULT NULL,
                                       `partner_id` bigint(20) unsigned DEFAULT NULL,
                                       `partner_object_uid` char(36) DEFAULT NULL,
                                       `partner_object_updated_at` timestamp NULL DEFAULT NULL,
                                       `name` varchar(100) NOT NULL,
                                       `reference` varchar(20) NOT NULL,
                                       `description` text DEFAULT NULL,
                                       `created_at` timestamp NULL DEFAULT NULL,
                                       `updated_at` timestamp NULL DEFAULT NULL,
                                       `responsible_user_id` bigint(20) unsigned DEFAULT NULL,
                                       `approved_at` timestamp NULL DEFAULT NULL,
                                       `not_applicable_at` timestamp NULL DEFAULT NULL,
                                       `max_sanction_fee` bigint(20) unsigned DEFAULT NULL,
                                       PRIMARY KEY (`id`),
                                       UNIQUE KEY `requirement_sources_uid_unique` (`uid`),
                                       UNIQUE KEY `requirement_sources_partner_object_uid_unique` (`partner_object_uid`),
                                       KEY `requirement_sources_partner_id_foreign` (`partner_id`),
                                       KEY `rs_rui` (`responsible_user_id`),
                                       CONSTRAINT `requirement_sources_partner_id_foreign` FOREIGN KEY (`partner_id`) REFERENCES `partners` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                                       CONSTRAINT `rs_rui` FOREIGN KEY (`responsible_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `requirements` (
                                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                `uid` char(36) DEFAULT NULL,
                                `requirement_source_id` bigint(20) unsigned NOT NULL,
                                `partner_object_uid` char(36) DEFAULT NULL,
                                `partner_object_updated_at` timestamp NULL DEFAULT NULL,
                                `iscontrol` tinyint(1) NOT NULL DEFAULT 0,
                                `applicable` tinyint(1) DEFAULT NULL,
                                `name` varchar(100) NOT NULL,
                                `reference` varchar(20) NOT NULL,
                                `ordinal` int(10) unsigned NOT NULL DEFAULT 0,
                                `description` text DEFAULT NULL,
                                `governance` text DEFAULT NULL,
                                `created_at` timestamp NULL DEFAULT NULL,
                                `updated_at` timestamp NULL DEFAULT NULL,
                                PRIMARY KEY (`id`),
                                UNIQUE KEY `requirements_uid_unique` (`uid`),
                                UNIQUE KEY `requirements_partner_object_uid_unique` (`partner_object_uid`),
                                KEY `requirements_requirement_source_id_foreign` (`requirement_source_id`),
                                CONSTRAINT `requirements_requirement_source_id_foreign` FOREIGN KEY (`requirement_source_id`) REFERENCES `requirement_sources` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `risk_level_mappings` (
                                       `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                       `probability_level_id` bigint(20) unsigned NOT NULL,
                                       `consequence_level_id` bigint(20) unsigned NOT NULL,
                                       `risk_level_id` bigint(20) unsigned NOT NULL,
                                       `created_at` timestamp NULL DEFAULT NULL,
                                       `updated_at` timestamp NULL DEFAULT NULL,
                                       PRIMARY KEY (`id`),
                                       KEY `risk_level_mappings_probability_level_id_foreign` (`probability_level_id`),
                                       KEY `risk_level_mappings_consequence_level_id_foreign` (`consequence_level_id`),
                                       KEY `risk_level_mappings_risk_level_foreign` (`risk_level_id`),
                                       CONSTRAINT `risk_level_mappings_consequence_level_id_foreign` FOREIGN KEY (`consequence_level_id`) REFERENCES `consequence_levels` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                       CONSTRAINT `risk_level_mappings_probability_level_id_foreign` FOREIGN KEY (`probability_level_id`) REFERENCES `probability_levels` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                       CONSTRAINT `risk_level_mappings_risk_level_foreign` FOREIGN KEY (`risk_level_id`) REFERENCES `risk_levels` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `risk_levels` (
                               `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                               `name` varchar(255) NOT NULL,
                               `description` text NOT NULL,
                               `ordinal` int(10) unsigned NOT NULL DEFAULT 0,
                               `color` varchar(6) NOT NULL,
                               `created_at` timestamp NULL DEFAULT NULL,
                               `updated_at` timestamp NULL DEFAULT NULL,
                               `reassessment_days_withoutplans` int(10) unsigned NOT NULL DEFAULT 365,
                               `reassessment_days_withplans` int(10) unsigned NOT NULL DEFAULT 180,
                               PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `risk_project_type_risk_templates` (
                                                    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                                    `uid` char(36) DEFAULT NULL,
                                                    `name` varchar(255) NOT NULL,
                                                    `risk_project_type_id` bigint(20) unsigned DEFAULT NULL,
                                                    `partner_object_uid` char(36) DEFAULT NULL,
                                                    `partner_object_updated_at` timestamp NULL DEFAULT NULL,
                                                    `scenariodescription` text DEFAULT NULL,
                                                    `consequencedescription` text DEFAULT NULL,
                                                    `probability_id` bigint(20) unsigned DEFAULT NULL,
                                                    `consequence_id` bigint(20) unsigned DEFAULT NULL,
                                                    `created_at` timestamp NULL DEFAULT NULL,
                                                    `updated_at` timestamp NULL DEFAULT NULL,
                                                    PRIMARY KEY (`id`),
                                                    UNIQUE KEY `risk_project_type_risk_templates_uid_unique` (`uid`),
                                                    UNIQUE KEY `risk_project_type_risk_templates_partner_object_uid_unique` (`partner_object_uid`),
                                                    KEY `rptrt_rpti_fk` (`risk_project_type_id`),
                                                    KEY `rptrt_pi_fk` (`probability_id`),
                                                    KEY `rptrt_ci_fk` (`consequence_id`),
                                                    CONSTRAINT `rptrt_ci_fk` FOREIGN KEY (`consequence_id`) REFERENCES `consequence_levels` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                                                    CONSTRAINT `rptrt_pi_fk` FOREIGN KEY (`probability_id`) REFERENCES `probability_levels` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                                                    CONSTRAINT `rptrt_rpti_fk` FOREIGN KEY (`risk_project_type_id`) REFERENCES `risk_project_types` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `risk_project_types` (
                                      `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                      `uid` char(36) DEFAULT NULL,
                                      `name` varchar(255) NOT NULL,
                                      `description` text NOT NULL,
                                      `partner_id` bigint(20) unsigned DEFAULT NULL,
                                      `partner_object_uid` char(36) DEFAULT NULL,
                                      `partner_object_updated_at` timestamp NULL DEFAULT NULL,
                                      `created_at` timestamp NULL DEFAULT NULL,
                                      `updated_at` timestamp NULL DEFAULT NULL,
                                      PRIMARY KEY (`id`),
                                      UNIQUE KEY `risk_project_types_uid_unique` (`uid`),
                                      UNIQUE KEY `risk_project_types_partner_object_uid_unique` (`partner_object_uid`),
                                      KEY `risk_project_types_partner_id_foreign` (`partner_id`),
                                      CONSTRAINT `risk_project_types_partner_id_foreign` FOREIGN KEY (`partner_id`) REFERENCES `partners` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `risk_project_user` (
                                     `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                     `risk_project_id` bigint(20) unsigned NOT NULL,
                                     `user_id` bigint(20) unsigned NOT NULL,
                                     `created_at` timestamp NULL DEFAULT NULL,
                                     `updated_at` timestamp NULL DEFAULT NULL,
                                     PRIMARY KEY (`id`),
                                     KEY `risk_project_user_risk_project_id_foreign` (`risk_project_id`),
                                     KEY `risk_project_user_user_id_foreign` (`user_id`),
                                     CONSTRAINT `risk_project_user_risk_project_id_foreign` FOREIGN KEY (`risk_project_id`) REFERENCES `risk_projects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                     CONSTRAINT `risk_project_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `risk_projects` (
                                 `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                 `name` varchar(255) NOT NULL,
                                 `scopedescription` text DEFAULT NULL,
                                 `purposedescription` text DEFAULT NULL,
                                 `responsible_user_id` bigint(20) unsigned NOT NULL,
                                 `department_id` bigint(20) unsigned NOT NULL,
                                 `start_date` date NOT NULL,
                                 `end_date` date DEFAULT NULL,
                                 `archived_at` timestamp NULL DEFAULT NULL,
                                 `created_at` timestamp NULL DEFAULT NULL,
                                 `updated_at` timestamp NULL DEFAULT NULL,
                                 `risk_project_type_id` bigint(20) unsigned DEFAULT NULL,
                                 PRIMARY KEY (`id`),
                                 KEY `risk_projects_responsible_user_id_foreign` (`responsible_user_id`),
                                 KEY `risk_projects_department_id_foreign` (`department_id`),
                                 KEY `rp_rpti_fk` (`risk_project_type_id`),
                                 CONSTRAINT `risk_projects_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON UPDATE CASCADE,
                                 CONSTRAINT `risk_projects_responsible_user_id_foreign` FOREIGN KEY (`responsible_user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
                                 CONSTRAINT `rp_rpti_fk` FOREIGN KEY (`risk_project_type_id`) REFERENCES `risk_project_types` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `risk_template_evaluation_attempts` (
                                                     `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                                     `context_type` varchar(255) NOT NULL,
                                                     `context_id` varchar(255) DEFAULT NULL,
                                                     `partner_id` bigint(20) unsigned NOT NULL,
                                                     `partner_template_uid` char(36) NOT NULL,
                                                     `partner_template_updated` timestamp NOT NULL,
                                                     `created_at` timestamp NULL DEFAULT NULL,
                                                     `updated_at` timestamp NULL DEFAULT NULL,
                                                     PRIMARY KEY (`id`),
                                                     KEY `rteapi` (`partner_id`),
                                                     CONSTRAINT `rteapi` FOREIGN KEY (`partner_id`) REFERENCES `partners` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `risks` (
                         `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                         `uid` char(36) DEFAULT NULL,
                         `name` varchar(255) NOT NULL,
                         `context_type` varchar(255) DEFAULT NULL,
                         `department_id` bigint(20) unsigned NOT NULL,
                         `context_id` bigint(20) unsigned DEFAULT NULL,
                         `partner_id` bigint(20) unsigned DEFAULT NULL,
                         `partner_object_uid` char(36) DEFAULT NULL,
                         `partner_object_updated_at` timestamp NULL DEFAULT NULL,
                         `scenariodescription` text DEFAULT NULL,
                         `consequencedescription` text DEFAULT NULL,
                         `riskowner_id` bigint(20) unsigned DEFAULT NULL,
                         `replacing_id` bigint(20) unsigned DEFAULT NULL,
                         `replacedby_id` bigint(20) unsigned DEFAULT NULL,
                         `assessed_at` datetime DEFAULT NULL,
                         `replaced_at` datetime DEFAULT NULL,
                         `created_at` timestamp NULL DEFAULT NULL,
                         `updated_at` timestamp NULL DEFAULT NULL,
                         `created_by` bigint(20) unsigned DEFAULT NULL,
                         `probability_id` bigint(20) unsigned DEFAULT NULL,
                         `consequence_id` bigint(20) unsigned DEFAULT NULL,
                         `assessmentcomment` text DEFAULT NULL,
                         `risk_project_id` bigint(20) unsigned DEFAULT NULL,
                         `post_probability_id` bigint(20) unsigned DEFAULT NULL,
                         `post_consequence_id` bigint(20) unsigned DEFAULT NULL,
                         PRIMARY KEY (`id`),
                         UNIQUE KEY `risks_uid_unique` (`uid`),
                         KEY `risks_department_foreign` (`department_id`),
                         KEY `risks_riskowner_id_foreign` (`riskowner_id`),
                         KEY `risks_replacing_id_foreign` (`replacing_id`),
                         KEY `risks_replacedby_id_foreign` (`replacedby_id`),
                         KEY `risks_created_by_foreign` (`created_by`),
                         KEY `risks_probability_id_foreign` (`probability_id`),
                         KEY `risks_consequence_id_foreign` (`consequence_id`),
                         KEY `risks_partner_id_foreign` (`partner_id`),
                         KEY `risks_risk_project_id_foreign` (`risk_project_id`),
                         KEY `risks_post_probability_id_foreign` (`post_probability_id`),
                         KEY `risks_post_consequence_id_foreign` (`post_consequence_id`),
                         CONSTRAINT `risks_consequence_id_foreign` FOREIGN KEY (`consequence_id`) REFERENCES `consequence_levels` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                         CONSTRAINT `risks_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                         CONSTRAINT `risks_department_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON UPDATE CASCADE,
                         CONSTRAINT `risks_partner_id_foreign` FOREIGN KEY (`partner_id`) REFERENCES `partners` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                         CONSTRAINT `risks_post_consequence_id_foreign` FOREIGN KEY (`post_consequence_id`) REFERENCES `consequence_levels` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                         CONSTRAINT `risks_post_probability_id_foreign` FOREIGN KEY (`post_probability_id`) REFERENCES `probability_levels` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                         CONSTRAINT `risks_probability_id_foreign` FOREIGN KEY (`probability_id`) REFERENCES `probability_levels` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                         CONSTRAINT `risks_replacedby_id_foreign` FOREIGN KEY (`replacedby_id`) REFERENCES `risks` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                         CONSTRAINT `risks_replacing_id_foreign` FOREIGN KEY (`replacing_id`) REFERENCES `risks` (`id`) ON UPDATE CASCADE,
                         CONSTRAINT `risks_risk_project_id_foreign` FOREIGN KEY (`risk_project_id`) REFERENCES `risk_projects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                         CONSTRAINT `risks_riskowner_id_foreign` FOREIGN KEY (`riskowner_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `role_competence` (
                                   `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                   `competence_id` bigint(20) unsigned NOT NULL,
                                   `role_id` bigint(20) unsigned NOT NULL,
                                   `acceptable_competence_level_id` bigint(20) unsigned DEFAULT NULL,
                                   `desired_competence_level_id` bigint(20) unsigned DEFAULT NULL,
                                   `created_at` timestamp NULL DEFAULT NULL,
                                   `updated_at` timestamp NULL DEFAULT NULL,
                                   PRIMARY KEY (`id`),
                                   KEY `role_competence_competence_id_foreign` (`competence_id`),
                                   KEY `role_competence_role_id_foreign` (`role_id`),
                                   KEY `role_competence_acceptable_competence_level_id_foreign` (`acceptable_competence_level_id`),
                                   KEY `role_competence_desired_competence_level_id_foreign` (`desired_competence_level_id`),
                                   CONSTRAINT `role_competence_acceptable_competence_level_id_foreign` FOREIGN KEY (`acceptable_competence_level_id`) REFERENCES `competence_levels` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                   CONSTRAINT `role_competence_competence_id_foreign` FOREIGN KEY (`competence_id`) REFERENCES `competences` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                   CONSTRAINT `role_competence_desired_competence_level_id_foreign` FOREIGN KEY (`desired_competence_level_id`) REFERENCES `competence_levels` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                   CONSTRAINT `role_competence_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `role_user` (
                             `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                             `role_id` bigint(20) unsigned NOT NULL,
                             `user_id` bigint(20) unsigned NOT NULL,
                             `created_at` timestamp NULL DEFAULT NULL,
                             `updated_at` timestamp NULL DEFAULT NULL,
                             PRIMARY KEY (`id`),
                             KEY `role_user_role_id_foreign` (`role_id`),
                             KEY `role_user_user_id_foreign` (`user_id`),
                             CONSTRAINT `role_user_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                             CONSTRAINT `role_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `roles` (
                         `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                         `uid` char(36) DEFAULT NULL,
                         `name` varchar(255) NOT NULL,
                         `description` longtext DEFAULT NULL,
                         `created_at` timestamp NULL DEFAULT NULL,
                         `updated_at` timestamp NULL DEFAULT NULL,
                         `external_provider_group_id` bigint(20) unsigned DEFAULT NULL,
                         `authorities` longtext DEFAULT NULL,
                         PRIMARY KEY (`id`),
                         UNIQUE KEY `roles_name_unique` (`name`),
                         UNIQUE KEY `roles_uid_unique` (`uid`),
                         KEY `roles_external_provider_group_id_foreign` (`external_provider_group_id`),
                         CONSTRAINT `roles_external_provider_group_id_foreign` FOREIGN KEY (`external_provider_group_id`) REFERENCES `external_provider_groups` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `sessions` (
                            `id` varchar(255) NOT NULL,
                            `user_id` bigint(20) unsigned DEFAULT NULL,
                            `ip_address` varchar(45) DEFAULT NULL,
                            `user_agent` text DEFAULT NULL,
                            `payload` longtext NOT NULL,
                            `last_activity` int(11) NOT NULL,
                            PRIMARY KEY (`id`),
                            KEY `sessions_user_id_index` (`user_id`),
                            KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `sites` (
                         `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                         `uid` char(36) DEFAULT NULL,
                         `name` varchar(255) NOT NULL,
                         `description` mediumtext DEFAULT NULL,
                         `responsible_user_id` bigint(20) unsigned DEFAULT NULL,
                         `external_provider_group_id` bigint(20) unsigned DEFAULT NULL,
                         `created_at` timestamp NULL DEFAULT NULL,
                         `updated_at` timestamp NULL DEFAULT NULL,
                         PRIMARY KEY (`id`),
                         UNIQUE KEY `sites_uid_unique` (`uid`),
                         KEY `sites_responsible_user_id_foreign` (`responsible_user_id`),
                         KEY `sites_external_provider_group_id_foreign` (`external_provider_group_id`),
                         CONSTRAINT `sites_external_provider_group_id_foreign` FOREIGN KEY (`external_provider_group_id`) REFERENCES `external_provider_groups` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                         CONSTRAINT `sites_responsible_user_id_foreign` FOREIGN KEY (`responsible_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `subject_categories` (
                                      `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                      `name` varchar(255) NOT NULL,
                                      `description` text NOT NULL,
                                      `created_at` timestamp NULL DEFAULT NULL,
                                      `updated_at` timestamp NULL DEFAULT NULL,
                                      PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `supplier_categories` (
                                       `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                       `name` varchar(255) NOT NULL,
                                       `description` longtext DEFAULT NULL,
                                       `reassessment_interval` varchar(255) DEFAULT NULL,
                                       `created_at` timestamp NULL DEFAULT NULL,
                                       `updated_at` timestamp NULL DEFAULT NULL,
                                       `partner_id` bigint(20) unsigned DEFAULT NULL,
                                       `partner_object_uid` char(36) DEFAULT NULL,
                                       `partner_object_updated_at` timestamp NULL DEFAULT NULL,
                                       `formulaname` varchar(255) DEFAULT NULL,
                                       `defaultvalue` tinyint(1) DEFAULT NULL,
                                       PRIMARY KEY (`id`),
                                       UNIQUE KEY `supplier_categories_partner_object_uid_unique` (`partner_object_uid`),
                                       KEY `supplier_categories_partner_id_foreign` (`partner_id`),
                                       CONSTRAINT `supplier_categories_partner_id_foreign` FOREIGN KEY (`partner_id`) REFERENCES `partners` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `supplier_documents` (
                                      `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                      `supplier_id` bigint(20) unsigned NOT NULL,
                                      `description` longtext DEFAULT NULL,
                                      `filename` varchar(255) NOT NULL,
                                      `contenttype` varchar(255) NOT NULL,
                                      `updated_by_name` varchar(255) NOT NULL,
                                      `created_at` timestamp NULL DEFAULT NULL,
                                      `updated_at` timestamp NULL DEFAULT NULL,
                                      `file` longblob DEFAULT NULL,
                                      PRIMARY KEY (`id`),
                                      KEY `supplier_documents_supplier_id_foreign` (`supplier_id`),
                                      CONSTRAINT `supplier_documents_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `supplier_requirements` (
                                         `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                         `name` varchar(255) NOT NULL,
                                         `description` longtext DEFAULT NULL,
                                         `supplier_category_id` bigint(20) unsigned NOT NULL,
                                         `reassessment` tinyint(1) NOT NULL DEFAULT 1,
                                         `created_at` timestamp NULL DEFAULT NULL,
                                         `updated_at` timestamp NULL DEFAULT NULL,
                                         `partner_object_uid` char(36) DEFAULT NULL,
                                         `partner_object_updated_at` timestamp NULL DEFAULT NULL,
                                         PRIMARY KEY (`id`),
                                         UNIQUE KEY `supplier_requirements_partner_object_uid_unique` (`partner_object_uid`),
                                         KEY `supplier_requirements_supplier_category_id_foreign` (`supplier_category_id`),
                                         CONSTRAINT `supplier_requirements_supplier_category_id_foreign` FOREIGN KEY (`supplier_category_id`) REFERENCES `supplier_categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `supplier_supplier_category` (
                                              `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                              `supplier_id` bigint(20) unsigned NOT NULL,
                                              `supplier_category_id` bigint(20) unsigned NOT NULL,
                                              `applicable` tinyint(1) NOT NULL,
                                              `updated_by_name` varchar(255) NOT NULL,
                                              `created_at` timestamp NULL DEFAULT NULL,
                                              `updated_at` timestamp NULL DEFAULT NULL,
                                              PRIMARY KEY (`id`),
                                              KEY `supplier_supplier_category_supplier_id_foreign` (`supplier_id`),
                                              KEY `supplier_supplier_category_supplier_category_id_foreign` (`supplier_category_id`),
                                              CONSTRAINT `supplier_supplier_category_supplier_category_id_foreign` FOREIGN KEY (`supplier_category_id`) REFERENCES `supplier_categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                              CONSTRAINT `supplier_supplier_category_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `supplier_supplier_requirement` (
                                                 `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                                 `supplier_id` bigint(20) unsigned NOT NULL,
                                                 `supplier_requirement_id` bigint(20) unsigned NOT NULL,
                                                 `updated_by_name` varchar(255) NOT NULL,
                                                 `note` longtext DEFAULT NULL,
                                                 `satisfactory` tinyint(1) NOT NULL,
                                                 `created_at` timestamp NULL DEFAULT NULL,
                                                 `updated_at` timestamp NULL DEFAULT NULL,
                                                 PRIMARY KEY (`id`),
                                                 KEY `supplier_supplier_requirement_supplier_id_foreign` (`supplier_id`),
                                                 KEY `supplier_supplier_requirement_supplier_requirement_id_foreign` (`supplier_requirement_id`),
                                                 CONSTRAINT `supplier_supplier_requirement_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                                 CONSTRAINT `supplier_supplier_requirement_supplier_requirement_id_foreign` FOREIGN KEY (`supplier_requirement_id`) REFERENCES `supplier_requirements` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `suppliers` (
                             `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                             `uid` char(36) DEFAULT NULL,
                             `name` varchar(255) NOT NULL,
                             `description` longtext DEFAULT NULL,
                             `responsible_user_id` bigint(20) unsigned DEFAULT NULL,
                             `created_at` timestamp NULL DEFAULT NULL,
                             `updated_at` timestamp NULL DEFAULT NULL,
                             `processoragreementdescription` longtext DEFAULT NULL,
                             `dataprocessor` tinyint(1) DEFAULT NULL,
                             `external_supplier_id` varchar(255) DEFAULT NULL,
                             PRIMARY KEY (`id`),
                             UNIQUE KEY `suppliers_name_unique` (`name`),
                             UNIQUE KEY `suppliers_uid_unique` (`uid`),
                             KEY `suppliers_responsible_user_id_foreign` (`responsible_user_id`),
                             CONSTRAINT `suppliers_responsible_user_id_foreign` FOREIGN KEY (`responsible_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `sustainability_aspect_sustainability_metric` (
                                                               `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                                               `sustainability_aspect_id` bigint(20) unsigned NOT NULL,
                                                               `sustainability_metric_id` bigint(20) unsigned NOT NULL,
                                                               `created_at` timestamp NULL DEFAULT NULL,
                                                               `updated_at` timestamp NULL DEFAULT NULL,
                                                               PRIMARY KEY (`id`),
                                                               KEY `sasmsai` (`sustainability_aspect_id`),
                                                               KEY `sasmsmi` (`sustainability_metric_id`),
                                                               CONSTRAINT `sasmsai` FOREIGN KEY (`sustainability_aspect_id`) REFERENCES `sustainability_aspects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                                               CONSTRAINT `sasmsmi` FOREIGN KEY (`sustainability_metric_id`) REFERENCES `sustainability_metrics` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `sustainability_aspects` (
                                          `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                          `name` varchar(255) NOT NULL,
                                          `description` mediumtext DEFAULT NULL,
                                          `threshold` bigint(20) DEFAULT NULL,
                                          `created_at` timestamp NULL DEFAULT NULL,
                                          `updated_at` timestamp NULL DEFAULT NULL,
                                          PRIMARY KEY (`id`),
                                          UNIQUE KEY `sustainability_aspects_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `sustainability_metric_levels` (
                                                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                                `sustainability_metric_id` bigint(20) unsigned NOT NULL,
                                                `name` varchar(255) NOT NULL,
                                                `description` mediumtext DEFAULT NULL,
                                                `multiplier` bigint(20) NOT NULL DEFAULT 1,
                                                `created_at` timestamp NULL DEFAULT NULL,
                                                `updated_at` timestamp NULL DEFAULT NULL,
                                                PRIMARY KEY (`id`),
                                                KEY `smlsmi` (`sustainability_metric_id`),
                                                CONSTRAINT `smlsmi` FOREIGN KEY (`sustainability_metric_id`) REFERENCES `sustainability_metrics` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `sustainability_metrics` (
                                          `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                          `name` varchar(255) NOT NULL,
                                          `description` mediumtext DEFAULT NULL,
                                          `created_at` timestamp NULL DEFAULT NULL,
                                          `updated_at` timestamp NULL DEFAULT NULL,
                                          PRIMARY KEY (`id`),
                                          UNIQUE KEY `sustainability_metrics_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `system_configurations` (
                                         `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                         `name` varchar(255) NOT NULL,
                                         `created_at` timestamp NULL DEFAULT NULL,
                                         `updated_at` timestamp NULL DEFAULT NULL,
                                         `value` mediumblob DEFAULT NULL,
                                         PRIMARY KEY (`id`),
                                         UNIQUE KEY `system_configurations_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `tags` (
                        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        `uid` char(36) DEFAULT NULL,
                        `name` varchar(25) NOT NULL,
                        `created_at` timestamp NULL DEFAULT NULL,
                        `updated_at` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `tags_uid_unique` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `user_competence` (
                                   `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                   `competence_id` bigint(20) unsigned NOT NULL,
                                   `user_id` bigint(20) unsigned NOT NULL,
                                   `competence_level_id` bigint(20) unsigned NOT NULL,
                                   `updated_by_name` varchar(255) NOT NULL,
                                   `note` longtext DEFAULT NULL,
                                   `created_at` timestamp NULL DEFAULT NULL,
                                   `updated_at` timestamp NULL DEFAULT NULL,
                                   PRIMARY KEY (`id`),
                                   KEY `user_competence_competence_id_foreign` (`competence_id`),
                                   KEY `user_competence_user_id_foreign` (`user_id`),
                                   KEY `user_competence_competence_level_id_foreign` (`competence_level_id`),
                                   CONSTRAINT `user_competence_competence_id_foreign` FOREIGN KEY (`competence_id`) REFERENCES `competences` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                   CONSTRAINT `user_competence_competence_level_id_foreign` FOREIGN KEY (`competence_level_id`) REFERENCES `competence_levels` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                   CONSTRAINT `user_competence_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `user_notification_channels` (
                                              `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                              `user_id` bigint(20) unsigned NOT NULL,
                                              `name` varchar(255) NOT NULL,
                                              `webhookurl` varchar(255) DEFAULT NULL,
                                              `email` tinyint(1) NOT NULL DEFAULT 0,
                                              `ignoremine` tinyint(1) NOT NULL DEFAULT 1,
                                              `scopes` longtext DEFAULT NULL CHECK (json_valid(`scopes`)),
                                              `events` longtext DEFAULT NULL CHECK (json_valid(`events`)),
                                              `created_at` timestamp NULL DEFAULT NULL,
                                              `updated_at` timestamp NULL DEFAULT NULL,
                                              PRIMARY KEY (`id`),
                                              KEY `user_notification_channels_user_id_foreign` (`user_id`),
                                              CONSTRAINT `user_notification_channels_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `user_notification_queue_entries` (
                                                   `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                                   `user_id` bigint(20) unsigned NOT NULL,
                                                   `user_notification_channel_id` bigint(20) unsigned NOT NULL,
                                                   `sender_id` bigint(20) unsigned DEFAULT NULL,
                                                   `title` longtext DEFAULT NULL,
                                                   `message` longtext DEFAULT NULL,
                                                   `clickurl` varchar(255) DEFAULT NULL,
                                                   `created_at` timestamp NULL DEFAULT NULL,
                                                   `updated_at` timestamp NULL DEFAULT NULL,
                                                   PRIMARY KEY (`id`),
                                                   KEY `user_notification_queue_entries_user_id_foreign` (`user_id`),
                                                   KEY `unqeunci` (`user_notification_channel_id`),
                                                   CONSTRAINT `unqeunci` FOREIGN KEY (`user_notification_channel_id`) REFERENCES `user_notification_channels` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                                   CONSTRAINT `user_notification_queue_entries_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `user_status_email_settings` (
                                              `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                              `user_id` bigint(20) unsigned NOT NULL,
                                              `monday` tinyint(1) NOT NULL DEFAULT 1,
                                              `tuesday` tinyint(1) NOT NULL DEFAULT 1,
                                              `wednesday` tinyint(1) NOT NULL DEFAULT 1,
                                              `thursday` tinyint(1) NOT NULL DEFAULT 1,
                                              `friday` tinyint(1) NOT NULL DEFAULT 1,
                                              `saturday` tinyint(1) NOT NULL DEFAULT 0,
                                              `sunday` tinyint(1) NOT NULL DEFAULT 0,
                                              `created_at` timestamp NULL DEFAULT NULL,
                                              `updated_at` timestamp NULL DEFAULT NULL,
                                              PRIMARY KEY (`id`),
                                              KEY `user_status_email_settings_user_id_foreign` (`user_id`),
                                              CONSTRAINT `user_status_email_settings_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `users` (
                         `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                         `uid` char(36) DEFAULT NULL,
                         `name` varchar(255) NOT NULL,
                         `email` varchar(255) NOT NULL,
                         `email_verified_at` timestamp NULL DEFAULT NULL,
                         `password` varchar(255) NOT NULL,
                         `enabled` tinyint(1) NOT NULL DEFAULT 0,
                         `remember_token` varchar(100) DEFAULT NULL,
                         `created_at` timestamp NULL DEFAULT NULL,
                         `updated_at` timestamp NULL DEFAULT NULL,
                         `external_id` varchar(255) DEFAULT NULL,
                         `title` varchar(255) DEFAULT NULL,
                         `manager_user_id` bigint(20) unsigned DEFAULT NULL,
                         `site_id` bigint(20) unsigned DEFAULT NULL,
                         `last_login_at` timestamp NULL DEFAULT NULL,
                         PRIMARY KEY (`id`),
                         UNIQUE KEY `users_email_unique` (`email`),
                         UNIQUE KEY `users_uid_unique` (`uid`),
                         UNIQUE KEY `users_external_id_unique` (`external_id`),
                         KEY `umui` (`manager_user_id`),
                         KEY `usi_fk` (`site_id`),
                         CONSTRAINT `umui` FOREIGN KEY (`manager_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                         CONSTRAINT `usi_fk` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE `vector_embeddings` (
                                     `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                     `embeddable_type` varchar(255) NOT NULL,
                                     `embeddable_id` bigint(20) unsigned NOT NULL,
                                     `provider` varchar(50) NOT NULL DEFAULT 'azure_openai',
                                     `embedding_model` varchar(100) DEFAULT NULL,
                                     `dimensions` int(10) unsigned DEFAULT NULL,
                                     `content_hash` char(40) DEFAULT NULL,
                                     `content` longtext DEFAULT NULL,
                                     `created_at` timestamp NULL DEFAULT NULL,
                                     `updated_at` timestamp NULL DEFAULT NULL,
                                     `embedding` vector(1536) DEFAULT NULL,
                                     PRIMARY KEY (`id`),
                                     UNIQUE KEY `ve_type_id_unique` (`embeddable_type`,`embeddable_id`),
                                     KEY `ve_provider_model_idx` (`provider`,`embedding_model`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;


SET
FOREIGN_KEY_CHECKS=1;
