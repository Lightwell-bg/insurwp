import type { Offer } from '../api/types';
import { formatMoney } from './format';
import { STRINGS } from './strings';

export interface OfferView {
  offer: Offer;
  isBest: boolean;
  meta: string;
}

/**
 * Готовит предложения к отображению.
 *
 * Список приходит отсортированным по цене, поэтому «выгоднее всего» — первое.
 * Когда предложение одно, отметка бессмысленна: сравнивать не с чем.
 */
export function toOfferViews(offers: Offer[]): OfferView[] {
  return offers.map((offer, index) => ({
    offer,
    isBest: index === 0 && offers.length > 1,
    meta: offer.limit_eur
      ? `${offer.territory_label} · ${STRINGS.limitLabel} ${formatMoney(offer.limit_eur, '€')}`
      : offer.territory_label,
  }));
}
