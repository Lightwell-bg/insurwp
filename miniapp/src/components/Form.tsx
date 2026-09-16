import type { FormEvent } from 'react';
import type { OptionsResponse } from '../api/types';
import type { FormValues } from '../lib/validate';
import { STRINGS } from '../lib/strings';
import { isInsideTelegram } from '../telegram/tg';

interface FormProps {
  options: OptionsResponse;
  values: FormValues;
  loading: boolean;
  onChange: (values: FormValues) => void;
  onSubmit: () => void;
}

export function Form({ options, values, loading, onChange, onSubmit }: FormProps) {
  const limit = options.limits[0];

  const handleSubmit = (event: FormEvent) => {
    event.preventDefault();
    onSubmit();
  };

  return (
    <form className="form" onSubmit={handleSubmit} noValidate>
      <label className="field">
        <span className="field__label">{STRINGS.fieldTerritory}</span>
        <select
          className="field__control"
          value={values.territory}
          onChange={(event) => onChange({ ...values, territory: event.target.value })}
        >
          {options.territories.map((item) => (
            <option key={item.value} value={item.value}>
              {item.label}
            </option>
          ))}
        </select>
      </label>

      <label className="field">
        <span className="field__label">{STRINGS.fieldTerm}</span>
        <select
          className="field__control"
          value={values.term}
          onChange={(event) => onChange({ ...values, term: event.target.value })}
        >
          {options.terms.map((item) => (
            <option key={item.value} value={item.value}>
              {item.label}
            </option>
          ))}
        </select>
      </label>

      <label className="field">
        <span className="field__label">{STRINGS.fieldStart}</span>
        <input
          className="field__control"
          type="date"
          value={values.date_start}
          min={options.dates.start_min}
          max={options.dates.start_max}
          onChange={(event) => onChange({ ...values, date_start: event.target.value })}
        />
      </label>

      <label className="field">
        <span className="field__label">{STRINGS.fieldBirth}</span>
        <input
          className="field__control"
          type="date"
          value={values.birth_date}
          min={options.dates.birth_min}
          max={options.dates.birth_max}
          onChange={(event) => onChange({ ...values, birth_date: event.target.value })}
        />
      </label>

      {limit ? (
        <p className="form__limit">
          {STRINGS.fieldLimit}: <b>{limit.label}</b>
        </p>
      ) : null}

      {/* Внутри Telegram отправку берёт на себя нативная главная кнопка. */}
      {isInsideTelegram() ? null : (
        <button className="button button--primary" type="submit" disabled={loading}>
          {loading ? STRINGS.calculating : STRINGS.submit}
        </button>
      )}
    </form>
  );
}
