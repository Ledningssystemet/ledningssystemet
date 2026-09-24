// JS
import './bootstrap.jsx';
import './authClient';
import './utils';
import './bpmneditor/index';
import './table';
import './documenteditor';
import 'dropzone';
import './aichat';
import './componentRegistry.jsx';
import { mountUiExtensionSlots } from './extensions/uiExtensionRegistry.jsx';

document.addEventListener('DOMContentLoaded', mountUiExtensionSlots);
