import './components/core/app.js';
import './bpmneditor/index.js';
import './documenteditor/index.js';
import './components/core/componentRegistry.jsx';
import { mountUiExtensionSlots } from './extensions/uiExtensionRegistry.jsx';

document.addEventListener('DOMContentLoaded', mountUiExtensionSlots);
