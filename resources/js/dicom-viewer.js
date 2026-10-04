import {
    Enums,
    RenderingEngine,
    init as cornerstoneInit,
} from '@cornerstonejs/core';
import dicomImageLoader from '@cornerstonejs/dicom-image-loader';

const viewers = document.querySelectorAll('[data-dicom-viewer]');

if (viewers.length) {
    await cornerstoneInit();
    await dicomImageLoader.init();
    dicomImageLoader.wadouri.register();

    viewers.forEach((element, index) => {
        initialiseViewer(element, index);
    });
}

async function initialiseViewer(element, index) {
    const canvas = element.querySelector('[data-dicom-canvas]');
    const status = element.querySelector('[data-dicom-status]');
    const renderingEngineId = `radiology-engine-${index}`;
    const viewportId = `radiology-viewport-${index}`;
    const renderingEngine = new RenderingEngine(renderingEngineId);

    renderingEngine.enableElement({
        viewportId,
        type: Enums.ViewportType.STACK,
        element: canvas,
        defaultOptions: { background: [0, 0, 0] },
    });

    const viewport = renderingEngine.getViewport(viewportId);
    const imageId = `wadouri:${element.dataset.imageUrl}`;
    try {
        await viewport.setStack([imageId]);
        viewport.render();
        status.textContent = 'DICOM ready';
        element.querySelector('[data-dicom-reset]')?.addEventListener('click', () => {
            viewport.resetCamera();
            viewport.resetProperties();
            viewport.render();
        });
        element.querySelector('[data-dicom-invert]')?.addEventListener('click', () => {
            const properties = viewport.getProperties();
            viewport.setProperties({ invert: !properties.invert });
            viewport.render();
        });
    } catch (error) {
        console.error('DICOM viewer failed to load', error);
        status.textContent = 'Unable to render DICOM file';
    }
}