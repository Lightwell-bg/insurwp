import { useEffect } from 'react';
import type { TelegramThemeParams } from '../types/telegram';
import { getWebApp } from './tg';

const THEME_KEYS: (keyof TelegramThemeParams)[] = [
  'bg_color',
  'text_color',
  'hint_color',
  'link_color',
  'button_color',
  'button_text_color',
  'secondary_bg_color',
  'header_bg_color',
  'section_bg_color',
  'section_separator_color',
  'subtitle_text_color',
  'destructive_text_color',
];

function applyThemeParams(params: TelegramThemeParams): void {
  const root = document.documentElement;

  for (const key of THEME_KEYS) {
    const value = params[key];

    if (value) {
      root.style.setProperty(`--tg-theme-${key.replace(/_/g, '-')}`, value);
    }
  }
}

/**
 * Переносит палитру Telegram в CSS-переменные и следит за её сменой.
 *
 * Пользователь может переключить тему, не закрывая мини-апп, поэтому одной
 * инициализации мало — подписываемся на событие themeChanged.
 */
export function useTelegramTheme(): void {
  useEffect(() => {
    const tg = getWebApp();

    if (!tg) {
      return;
    }

    const sync = () => {
      applyThemeParams(tg.themeParams);
      document.documentElement.dataset.theme = tg.colorScheme;

      const background = tg.themeParams.bg_color;

      if (background) {
        tg.setHeaderColor?.(background);
        tg.setBackgroundColor?.(background);
      }
    };

    sync();
    tg.onEvent?.('themeChanged', sync);

    return () => tg.offEvent?.('themeChanged', sync);
  }, []);
}
