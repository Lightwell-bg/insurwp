import { useEffect, useRef } from 'react';
import { BRAND } from '../lib/brand';
import { getWebApp } from './tg';

interface MainButtonOptions {
  text: string;
  visible: boolean;
  loading: boolean;
  onClick: () => void;
}

/**
 * Управляет нативной главной кнопкой Telegram.
 *
 * Обработчик держим в ref: Telegram не заменяет подписку при повторном вызове
 * onClick, а снимать и ставить её на каждый рендер — лишние операции и риск
 * потерять клик между ними.
 */
export function useMainButton({ text, visible, loading, onClick }: MainButtonOptions): void {
  const handler = useRef(onClick);
  handler.current = onClick;

  useEffect(() => {
    const button = getWebApp()?.MainButton;

    if (!button) {
      return;
    }

    const click = () => handler.current();

    button.onClick(click);

    return () => button.offClick(click);
  }, []);

  useEffect(() => {
    const button = getWebApp()?.MainButton;

    if (!button) {
      return;
    }

    button.setText(text);
    // Красим нативную кнопку в свой цвет: без этого она берёт цвет темы
    // пользователя, который может оказаться блёклым системным синим.
    button.setParams?.({ color: BRAND.rose, text_color: BRAND.white });

    if (visible) {
      button.show();
    } else {
      button.hide();
    }

    if (loading) {
      button.showProgress(false);
      button.disable();
    } else {
      button.hideProgress();
      button.enable();
    }
  }, [text, visible, loading]);
}
