import { STRINGS } from '../lib/strings';
import type { OptionsResponse, QuoteRequest, QuoteResponse } from './types';

const BASE = (import.meta.env.VITE_INSURWP_API_BASE ?? '').replace(/\/+$/, '');
const TIMEOUT_MS = 15000;

async function request<T>(path: string, init?: RequestInit): Promise<T> {
  const controller = new AbortController();
  const timer = window.setTimeout(() => controller.abort(), TIMEOUT_MS);

  try {
    const response = await fetch(BASE + path, {
      ...init,
      // Маршруты публичные: cookie не нужны, а nonce WordPress с чужого домена
      // всё равно не получить.
      credentials: 'omit',
      signal: controller.signal,
    });

    const body = await response.json().catch(() => null);

    if (!response.ok) {
      // Ошибки валидации приходят как {code, message} с кодом 400.
      throw new Error(body?.message || STRINGS.error);
    }

    return body as T;
  } catch (error) {
    if (error instanceof DOMException && error.name === 'AbortError') {
      throw new Error(STRINGS.errorTimeout);
    }

    // fetch бросает TypeError и при обрыве сети, и когда браузер заблокировал
    // ответ из-за CORS — для пользователя это одно и то же.
    if (error instanceof TypeError) {
      throw new Error(STRINGS.errorNetwork);
    }

    throw error;
  } finally {
    window.clearTimeout(timer);
  }
}

export function fetchOptions(): Promise<OptionsResponse> {
  return request<OptionsResponse>('/options');
}

export function fetchQuote(payload: QuoteRequest): Promise<QuoteResponse> {
  return request<QuoteResponse>('/quote', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  });
}
