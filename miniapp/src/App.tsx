import { useCallback, useEffect, useState } from 'react';
import { fetchOptions, fetchQuote } from './api/client';
import type { OptionsResponse, QuoteResponse } from './api/types';
import { ErrorBanner } from './components/ErrorBanner';
import { Form } from './components/Form';
import { IconShield } from './components/icons';
import { Results } from './components/Results';
import { Services } from './components/Services';
import { Skeleton } from './components/Skeleton';
import { STRINGS } from './lib/strings';
import type { FormValues } from './lib/validate';
import { validate } from './lib/validate';
import { isInsideTelegram, notify } from './telegram/tg';
import { useBackButton } from './telegram/useBackButton';
import { useMainButton } from './telegram/useMainButton';
import { useTelegramTheme } from './telegram/useTelegramTheme';

type Screen = 'form' | 'results';

export function App() {
  useTelegramTheme();

  const [options, setOptions] = useState<OptionsResponse | null>(null);
  const [optionsError, setOptionsError] = useState<string | null>(null);
  const [values, setValues] = useState<FormValues>({
    term: '',
    territory: 'all',
    date_start: '',
    birth_date: '',
  });
  const [screen, setScreen] = useState<Screen>('form');
  const [quote, setQuote] = useState<QuoteResponse | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const loadOptions = useCallback(async () => {
    setOptionsError(null);

    try {
      const data = await fetchOptions();

      setOptions(data);
      setValues((current) => ({
        ...current,
        term: current.term || data.default_term,
        date_start: current.date_start || data.dates.today,
      }));
    } catch (cause) {
      setOptionsError(cause instanceof Error ? cause.message : STRINGS.errorOptions);
    }
  }, []);

  useEffect(() => {
    void loadOptions();
  }, [loadOptions]);

  const submit = useCallback(async () => {
    if (!options || loading) {
      return;
    }

    const message = validate(values, options.dates);

    if (message) {
      setError(message);
      notify('error');
      return;
    }

    setError(null);
    setLoading(true);
    setScreen('results');

    try {
      const result = await fetchQuote(values);

      setQuote(result);
      notify('success');
    } catch (cause) {
      // Возвращаем на форму: ошибку нужно показать рядом с полями,
      // которые пользователь будет править.
      setQuote(null);
      setScreen('form');
      setError(cause instanceof Error ? cause.message : STRINGS.error);
      notify('error');
    } finally {
      setLoading(false);
    }
  }, [options, values, loading]);

  const backToForm = useCallback(() => setScreen('form'), []);

  const handleMainButton = useCallback(() => {
    if (screen === 'form') {
      void submit();
      return;
    }

    backToForm();
  }, [screen, submit, backToForm]);

  useMainButton({
    text: screen === 'form' ? STRINGS.submit : STRINGS.recalc,
    visible: Boolean(options),
    loading,
    onClick: handleMainButton,
  });

  useBackButton(screen === 'results', backToForm);

  return (
    <main className="app">
      <header className="app__head">
        <span className="app__mark">
          <IconShield width={22} height={22} />
        </span>
        <div>
          <h1 className="app__title">{STRINGS.title}</h1>
          <p className="app__subtitle">{STRINGS.subtitle}</p>
        </div>
      </header>

      {optionsError ? <ErrorBanner message={optionsError} onRetry={() => void loadOptions()} /> : null}

      {!options && !optionsError ? <p className="app__loading">{STRINGS.loadingOptions}</p> : null}

      {options && screen === 'form' ? (
        <>
          {error ? <ErrorBanner message={error} /> : null}
          <Form options={options} values={values} loading={loading} onChange={setValues} onSubmit={() => void submit()} />
        </>
      ) : null}

      {options && screen === 'results' ? (
        <>
          {loading || !quote ? <Skeleton /> : <Results quote={quote} />}

          {/* Вне Telegram нативной главной кнопки нет — нужна обычная. */}
          {!isInsideTelegram() && !loading ? (
            <button className="button button--ghost" type="button" onClick={backToForm}>
              {STRINGS.recalc}
            </button>
          ) : null}
        </>
      ) : null}

      {options ? <Services /> : null}

      {options?.show_disclaimer ? <p className="app__note">{STRINGS.disclaimer}</p> : null}
    </main>
  );
}
