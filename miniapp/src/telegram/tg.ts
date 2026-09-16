import type { TelegramWebApp } from '../types/telegram';

export function getWebApp(): TelegramWebApp | undefined {
  return window.Telegram?.WebApp;
}

/**
 * Приложение открыто внутри Telegram.
 *
 * Проверять наличие window.Telegram бесполезно: SDK создаёт объект WebApp
 * на любой странице, где подключён. Настоящее окружение выдаёт себя платформой —
 * вне Telegram она равна 'unknown'.
 *
 * Вне Telegram нативных кнопок нет, поэтому интерфейс показывает свою кнопку.
 */
export function isInsideTelegram(): boolean {
  const platform = getWebApp()?.platform;

  return Boolean(platform) && platform !== 'unknown';
}

export function initWebApp(): void {
  const tg = getWebApp();

  tg?.ready();
  tg?.expand();
}

/**
 * Открывает внешнюю ссылку — оформление полиса или страницу услуги на сайте.
 *
 * try_instant_view отключён намеренно: страница оформления заполняет свою
 * форму параметрами из адреса собственным скриптом, а в режиме Instant View
 * он не выполняется и поля остаются пустыми. Для остальных ссылок это тоже
 * безопаснее — пользователь видит настоящий сайт, а не урезанную копию.
 */
export function openExternalLink(url: string): void {
  const tg = getWebApp();

  if (tg) {
    tg.openLink(url, { try_instant_view: false });
    return;
  }

  window.open(url, '_blank', 'noopener,noreferrer');
}

export function notify(type: 'success' | 'error'): void {
  getWebApp()?.HapticFeedback?.notificationOccurred(type);
}
