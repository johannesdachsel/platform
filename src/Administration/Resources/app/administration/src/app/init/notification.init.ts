import { handle } from '@shopware-ag/admin-extension-sdk/es/channel';

export default function initializeNotifications(): void {
    // Handle incoming notifications from the ExtensionAPI
    handle('notificationDispatch', async (notificationOptions) => {
        // @ts-expect-error
        const viewRoot = Shopware.Application.view.root;
        // @ts-expect-error
        const $tc = viewRoot.$tc.bind(viewRoot);

        const message = notificationOptions.message ?? $tc('global.notification.noMessage');
        const title = notificationOptions.title ?? $tc('global.notification.noTitle');
        const actions = notificationOptions.actions ?? [];
        const appearance = notificationOptions.appearance ?? 'notification';
        const growl = notificationOptions.growl ?? true;
        const variant = notificationOptions.variant ?? 'info';

        await Shopware.State.dispatch('notification/createNotification', {
            variant: variant,
            title: title,
            message: message,
            growl: growl,
            actions: actions,
            system: appearance === 'system',
        });
    });
}
