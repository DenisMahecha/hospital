import './bootstrap';

document.addEventListener('DOMContentLoaded', () => {
	const sidebar = document.querySelector('[data-sidebar]');
	const sidebarScrim = document.querySelector('[data-sidebar-scrim]');
	const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
	const closeSidebar = () => {
		sidebar?.classList.remove('is-open');
		sidebarScrim?.classList.remove('is-visible');
		sidebarToggle?.setAttribute('aria-expanded', 'false');
	};

	document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
		button.addEventListener('click', () => {
			const isOpen = sidebar?.classList.toggle('is-open') ?? false;
			sidebarScrim?.classList.toggle('is-visible', isOpen);
			button.setAttribute('aria-expanded', String(isOpen));
		});
	});
	sidebarScrim?.addEventListener('click', closeSidebar);
	sidebar?.querySelectorAll('a').forEach((link) => link.addEventListener('click', closeSidebar));
	document.addEventListener('keydown', (event) => {
		if (event.key === 'Escape') closeSidebar();
	});

	document.querySelectorAll('[data-dismiss]').forEach((button) => {
		button.addEventListener('click', () => button.closest('[data-dismissible]')?.remove());
	});

	document.querySelectorAll('[data-confirm]').forEach((form) => {
		form.addEventListener('submit', (event) => {
			if (!window.confirm(form.dataset.confirm)) event.preventDefault();
		});
	});

	const notificationTarget = document.querySelector('[data-notification-target]');
	const departmentTarget = document.querySelector('[data-notification-department]');
	const userTarget = document.querySelector('[data-notification-user]');
	const syncNotificationTarget = () => {
		const target = notificationTarget?.value;
		const isDepartment = target === 'department';
		const isUser = target === 'user';
		if (departmentTarget) departmentTarget.hidden = !isDepartment;
		if (userTarget) userTarget.hidden = !isUser;
		if (departmentTarget?.querySelector('select')) departmentTarget.querySelector('select').required = isDepartment;
		if (userTarget?.querySelector('select')) userTarget.querySelector('select').required = isUser;
	};
	notificationTarget?.addEventListener('change', syncNotificationTarget);
	syncNotificationTarget();
});
