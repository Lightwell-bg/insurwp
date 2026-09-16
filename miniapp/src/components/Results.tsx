import type { QuoteResponse } from '../api/types';
import { formatDate, formatYears } from '../lib/format';
import { toOfferViews } from '../lib/mapQuote';
import { STRINGS } from '../lib/strings';
import { OfferCard } from './OfferCard';

interface ResultsProps {
  quote: QuoteResponse;
}

export function Results({ quote }: ResultsProps) {
  const views = toOfferViews(quote.offers);

  return (
    <div className="results">
      <div className="summary">
        <span className="chip">
          {STRINGS.ageLabel}: <b>{`${quote.age} ${formatYears(quote.age)}`}</b>
        </span>
        <span className="chip">
          {STRINGS.termLabel}: <b>{quote.term_label || quote.term}</b>
        </span>
        <span className="chip">
          {STRINGS.startLabel}: <b>{formatDate(quote.date_start)}</b>
        </span>
      </div>

      {views.length === 0 ? (
        <div className="empty">
          <strong>{STRINGS.empty}</strong>
          <span>{STRINGS.emptyHint}</span>
        </div>
      ) : (
        <>
          <p className="results__count">
            {STRINGS.found} {views.length}
          </p>
          <ul className="offers">
            {views.map((view) => (
              <OfferCard
                key={`${view.offer.insurer_key}-${view.offer.territory}`}
                view={view}
                showBgn={quote.show_bgn}
              />
            ))}
          </ul>
        </>
      )}
    </div>
  );
}
