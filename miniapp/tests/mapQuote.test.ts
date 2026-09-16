import { describe, expect, it } from 'vitest';
import type { Offer } from '../src/api/types';
import { toOfferViews } from '../src/lib/mapQuote';

function offer(patch: Partial<Offer> = {}): Offer {
  return {
    insurer_key: 'uniqa',
    insurer_name: 'UNIQA',
    territory: 'bulgaria',
    territory_label: 'Болгария',
    age_group: '0_69',
    price_eur: 89.7,
    price_bgn: 175.44,
    limit_eur: 30677.51,
    official_url: '',
    order_url: 'https://bginfo.eu/insur/?ins_type=1',
    ...patch,
  };
}

describe('toOfferViews', () => {
  it('отмечает первое предложение как самое выгодное', () => {
    const views = toOfferViews([offer(), offer({ insurer_key: 'bulstrad_life', price_eur: 98.67 })]);

    expect(views[0].isBest).toBe(true);
    expect(views[1].isBest).toBe(false);
  });

  it('не отмечает единственное предложение — сравнивать не с чем', () => {
    expect(toOfferViews([offer()])[0].isBest).toBe(false);
  });

  it('добавляет лимит в подпись', () => {
    expect(toOfferViews([offer()])[0].meta).toBe('Болгария · Лимит 30677,51 €');
  });

  it('обходится без лимита, когда его нет', () => {
    expect(toOfferViews([offer({ limit_eur: null })])[0].meta).toBe('Болгария');
  });

  it('не падает на пустом списке', () => {
    expect(toOfferViews([])).toEqual([]);
  });
});
