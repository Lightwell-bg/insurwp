import type { OfferView } from '../lib/mapQuote';
import { formatMoney } from '../lib/format';
import { STRINGS } from '../lib/strings';
import { openExternalLink } from '../telegram/tg';

interface OfferCardProps {
  view: OfferView;
  showBgn: boolean;
}

export function OfferCard({ view, showBgn }: OfferCardProps) {
  const { offer, isBest, meta } = view;

  return (
    <li className={`offer${isBest ? ' offer--best' : ''}`}>
      <div className="offer__main">
        <div className="offer__name">
          {offer.insurer_name}
          {isBest ? <span className="offer__badge">{STRINGS.best}</span> : null}
        </div>
        <div className="offer__meta">{meta}</div>
      </div>

      <div className="offer__price">
        <div className="offer__price-eur">{formatMoney(offer.price_eur, '€')}</div>
        {showBgn ? <div className="offer__price-bgn">{formatMoney(offer.price_bgn, 'лв')}</div> : null}
        <div className="offer__price-note">{STRINGS.perPolicy}</div>
      </div>

      {/* Акцент — только у самого выгодного предложения: четыре одинаково
          яркие кнопки не помогают выбрать. */}
      <button
        className={`button ${isBest ? 'button--order' : 'button--ghost'}`}
        type="button"
        onClick={() => openExternalLink(offer.order_url)}
      >
        {STRINGS.order}
      </button>
    </li>
  );
}
