import { describe, expect, it } from 'vitest';
import { formatDate, formatMoney, formatYears } from '../src/lib/format';

describe('formatMoney', () => {
  it('печатает две дробные цифры с запятой', () => {
    expect(formatMoney(89.7, '€')).toBe('89,70 €');
    expect(formatMoney(175.444, 'лв')).toBe('175,44 лв');
  });

  it('не печатает мусор вместо числа', () => {
    expect(formatMoney(Number.NaN, '€')).toBe('');
  });
});

describe('formatDate', () => {
  it('переводит Y-m-d в d.m.Y', () => {
    expect(formatDate('2026-08-06')).toBe('06.08.2026');
  });

  it('возвращает исходное значение, если формат неожиданный', () => {
    expect(formatDate('06.08.2026')).toBe('06.08.2026');
    expect(formatDate('')).toBe('');
  });
});

describe('formatYears', () => {
  it('склоняет по последней цифре', () => {
    expect(formatYears(1)).toBe('год');
    expect(formatYears(33)).toBe('года');
    expect(formatYears(36)).toBe('лет');
    expect(formatYears(21)).toBe('год');
  });

  it('держит исключение для 11-14', () => {
    expect(formatYears(11)).toBe('лет');
    expect(formatYears(12)).toBe('лет');
    expect(formatYears(114)).toBe('лет');
  });
});
