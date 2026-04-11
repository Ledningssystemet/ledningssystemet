import { SwaggerUIBundle } from 'swagger-ui-dist';
import 'swagger-ui-dist/swagger-ui.css';

const container = document.getElementById('swagger-ui');

if (container instanceof HTMLElement) {
    const specUrl = container.dataset.specUrl;

    SwaggerUIBundle({
        domNode: container,
        url: specUrl,
        deepLinking: true,
        displayRequestDuration: true,
        docExpansion: 'list',
        filter: true,
        persistAuthorization: true,
        defaultModelsExpandDepth: 1,
        defaultModelExpandDepth: 1,
    });
}

