/**
 * Форматирование значений для интерфейса.
 */

/**
 * Сумма в виде «89,70 €».
 */
export function formatMoney(value: number, currency: string): string {
  if (!Number.isFinite(value)) {
    return '';
  }

  return `${value.toFixed(2).replace('.', ',')} ${currency}`;
}

/**
 * Дата из Y-m-d в привычный d.m.Y.
 */
export function formatDate(iso: string): string {
  const parts = String(iso ?? '').split('-');

  if (parts.length !== 3) {
    return String(iso ?? '');
  }

  return `${parts[2]}.${parts[1]}.${parts[0]}`;
}

/**
 * Склонение слова «год» по числу лет.
 */
export function formatYears(age: number): string {
  const tail100 = Math.abs(age) % 100;
  const tail10 = tail100 % 10;

  if (tail100 >= 11 && tail100 <= 14) {
    return 'лет';
  }

  if (tail10 === 1) {
    return 'год';
  }

  if (tail10 >= 2 && tail10 <= 4) {
    return 'года';
  }

  return 'лет';
}
