import { useEffect, useEffectEvent } from 'react';
import echo from '@/echo';

export type DashboardStatsUpdatedEvent = {
    userId: number;
    submissionId: string;
};

type UseDashboardChannelOptions = {
    onDashboardStatsUpdated?: (event: DashboardStatsUpdatedEvent) => void;
};

export function useDashboardChannel(
    userId: number,
    options: UseDashboardChannelOptions = {},
): void {
    const handleDashboardStatsUpdated = useEffectEvent(
        (event: DashboardStatsUpdatedEvent) => {
            options.onDashboardStatsUpdated?.(event);
        },
    );

    useEffect(() => {
        const currentEcho = echo;

        if (currentEcho == null || !userId) {
            return;
        }

        const channelName = `App.Models.User.${userId}`;
        const channel = currentEcho.private(channelName);

        channel.listen('.dashboard.stats-updated', handleDashboardStatsUpdated);

        return () => {
            channel.stopListening('.dashboard.stats-updated');
            currentEcho.leave(channelName);
        };
    }, [userId]);
}
