import { describe, expect, it } from 'vitest';
import type { DateBounds } from '../src/api/types';
import { STRINGS } from '../src/lib/strings';
import type { FormValues } from '../src/lib/validate';
import { validate } from '../src/lib/validate';

const DATES: DateBounds = {
  today: '2026-09-15',
  start_min: '2026-09-15',
  start_max: '2027-09-15',
  birth_min: '1941-09-15',
  birth_max: '2026-09-15',
};

const VALID: FormValues = {
  term: '12 месяцев',
  territory: 'all',
  date_start: '2026-10-01',
  birth_date: '1990-07-01',
};

describe('validate', () => {
  it('пропускает корректную форму', () => {
    expect(validate(VALID, DATES)).toBeNull();
  });

  it('требует заполнить поля', () => {
    expect(validate({ ...VALID, term: '' }, DATES)).toBe(STRINGS.errorNoTerm);
    expect(validate({ ...VALID, date_start: '' }, DATES)).toBe(STRINGS.errorNoStart);
    expect(validate({ ...VALID, birth_date: '' }, DATES)).toBe(STRINGS.errorNoBirth);
  });

  it('держит дату начала в границах', () => {
    expect(validate({ ...VALID, date_start: '2026-09-14' }, DATES)).toBe(STRINGS.errorStartRange);
    expect(validate({ ...VALID, date_start: '2027-09-16' }, DATES)).toBe(STRINGS.errorStartRange);
    expect(validate({ ...VALID, date_start: DATES.start_min }, DATES)).toBeNull();
    expect(validate({ ...VALID, date_start: DATES.start_max }, DATES)).toBeNull();
  });

  it('отсекает недопустимый возраст', () => {
    expect(validate({ ...VALID, birth_date: '2026-09-16' }, DATES)).toBe(STRINGS.errorBirthFuture);
    expect(validate({ ...VALID, birth_date: '1941-09-14' }, DATES)).toBe(STRINGS.errorBirthTooOld);
  });

  it('не даёт родиться позже начала полиса', () => {
    // Границы приходят с сервера, поэтому функция не должна полагаться на то,
    // что начало полиса всегда сегодня или позже.
    const dates: DateBounds = { ...DATES, start_min: '2026-01-01' };

    expect(validate({ ...VALID, date_start: '2026-01-10', birth_date: '2026-01-20' }, dates)).toBe(
      STRINGS.errorBirthAfterStart,
    );
  });
});
