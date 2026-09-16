import { useEffect, useRef } from 'react';
import { getWebApp } from './tg';

/**
 * Управляет нативной кнопкой «Назад» Telegram.
 *
 * Показываем её только на экране результатов: с формы уходить некуда,
 * там кнопка должна закрывать приложение штатным способом Telegram.
 */
export function useBackButton(visible: boolean, onClick: () => void): void {
  const handler = useRef(onClick);
  handler.current = onClick;

  useEffect(() => {
    const button = getWebApp()?.BackButton;

    if (!button) {
      return;
    }

    const click = () => handler.current();

    button.onClick(click);

    return () => {
      button.offClick(click);
      button.hide();
    };
  }, []);

  useEffect(() => {
    const button = getWebApp()?.BackButton;

    if (!button) {
      return;
    }

    if (visible) {
      button.show();
    } else {
      button.hide();
    }
  }, [visible]);
}
