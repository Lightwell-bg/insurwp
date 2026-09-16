import { STRINGS } from '../lib/strings';

interface ErrorBannerProps {
  message: string;
  onRetry?: () => void;
}

export function ErrorBanner({ message, onRetry }: ErrorBannerProps) {
  return (
    <div className="error" role="alert">
      <span>{message}</span>
      {onRetry ? (
        <button className="button button--ghost" type="button" onClick={onRetry}>
          {STRINGS.retry}
        </button>
      ) : null}
    </div>
  );
}
