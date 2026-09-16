import { useState } from 'react';
import { SERVICE_CTA, SERVICE_GROUPS } from '../data/services';
import { openExternalLink } from '../telegram/tg';
import { IconBriefcase, IconChevron, IconIdCard, IconStar } from './icons';

const GROUP_ICON = {
  'id-card': IconIdCard,
  star: IconStar,
  briefcase: IconBriefcase,
} as const;

export function Services() {
  const [openGroup, setOpenGroup] = useState<string | null>(null);

  return (
    <section className="services">
      <h2 className="services__title">Что дальше</h2>

      <div className="service-groups">
        {SERVICE_GROUPS.map((group) => {
          const Icon = GROUP_ICON[group.icon];
          const isOpen = openGroup === group.id;

          return (
            <div className="service-group" key={group.id}>
              <button
                type="button"
                className="service-group__head"
                aria-expanded={isOpen}
                onClick={() => setOpenGroup(isOpen ? null : group.id)}
              >
                <span className={`service-group__icon service-group__icon--${group.accent}`}>
                  <Icon width={18} height={18} />
                </span>
                <span className="service-group__title">{group.title}</span>
                <span className="service-group__count">{group.links.length}</span>
                <IconChevron className={`service-group__chevron${isOpen ? ' service-group__chevron--open' : ''}`} />
              </button>

              {isOpen ? (
                <ul className="service-group__list">
                  {group.links.map((link) => (
                    <li key={link.url}>
                      <button type="button" className="service-link" onClick={() => openExternalLink(link.url)}>
                        <span>{link.label}</span>
                        <IconChevron className="service-link__chevron" />
                      </button>
                    </li>
                  ))}
                </ul>
              ) : null}
            </div>
          );
        })}
      </div>

      <div className="service-cta">
        <p className="service-cta__title">{SERVICE_CTA.title}</p>

        <button
          type="button"
          className="service-cta__link"
          onClick={() => openExternalLink(SERVICE_CTA.prices.url)}
        >
          <span>{SERVICE_CTA.prices.label}</span>
          <IconChevron />
        </button>

        <button
          type="button"
          className="service-cta__link service-cta__link--ghost"
          onClick={() => openExternalLink(SERVICE_CTA.contact.url)}
        >
          <span>{SERVICE_CTA.contact.label}</span>
          <IconChevron />
        </button>
      </div>
    </section>
  );
}
