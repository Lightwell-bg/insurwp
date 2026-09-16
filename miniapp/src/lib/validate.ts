import type { DateBounds } from '../api/types';
import { STRINGS } from './strings';

export interface FormValues {
  term: string;
  territory: string;
  date_start: string;
  birth_date: string;
}

/**
 * Проверяет форму перед отправкой.
 *
 * Границы приходят с сервера: возраст считается на дату начала полиса во времени
 * сайта, а устройство пользователя может стоять в другой таймзоне.
 * Даты в формате Y-m-d сравниваются как строки — порядок совпадает с хронологией.
 *
 * @returns Текст ошибки либо null, если всё в порядке.
 */
export function validate(values: FormValues, dates: DateBounds): string | null {
  if (!values.term) {
    return STRINGS.errorNoTerm;
  }

  if (!values.date_start) {
    return STRINGS.errorNoStart;
  }

  if (!values.birth_date) {
    return STRINGS.errorNoBirth;
  }

  if (values.date_start < dates.start_min || values.date_start > dates.start_max) {
    return STRINGS.errorStartRange;
  }

  if (values.birth_date > dates.birth_max) {
    return STRINGS.errorBirthFuture;
  }

  if (values.birth_date < dates.birth_min) {
    return STRINGS.errorBirthTooOld;
  }

  if (values.birth_date > values.date_start) {
    return STRINGS.errorBirthAfterStart;
  }

  return null;
}
