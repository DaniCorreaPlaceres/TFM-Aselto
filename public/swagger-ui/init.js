window.addEventListener('DOMContentLoaded', () => {
    window.SwaggerUIBundle({
        url: document.getElementById('swagger-ui').dataset.contractUrl,
        dom_id: '#swagger-ui',
        deepLinking: true,
        persistAuthorization: false,
        validatorUrl: null,
        queryConfigEnabled: false,
        presets: [window.SwaggerUIBundle.presets.apis],
    });
});
