import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

type WindowWithPusher = Window & typeof globalThis & { Pusher: typeof Pusher };

const hasReverbConfig =
    typeof window !== 'undefined' &&
    Boolean(
        import.meta.env.VITE_REVERB_APP_KEY &&
        import.meta.env.VITE_REVERB_HOST &&
        import.meta.env.VITE_REVERB_PORT,
    );

const windowWithPusher = window as WindowWithPusher;
windowWithPusher.Pusher = Pusher;

const echo = hasReverbConfig
    ? new Echo({
          broadcaster: 'reverb',
          key: import.meta.env.VITE_REVERB_APP_KEY,
          wsHost: import.meta.env.VITE_REVERB_HOST,
          wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 80),
          wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
          forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
          enabledTransports: ['ws', 'wss'],
      })
    : null;

export default echo;
