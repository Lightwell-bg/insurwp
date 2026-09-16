import { describe, expect, it } from 'vitest';
import { SERVICE_CTA, SERVICE_GROUPS } from '../src/data/services';

describe('SERVICE_GROUPS', () => {
  it('у каждой группы есть хотя бы одна ссылка', () => {
    for (const group of SERVICE_GROUPS) {
      expect(group.links.length).toBeGreaterThan(0);
    }
  });

  it('все ссылки ведут на bginfo.eu по https', () => {
    for (const group of SERVICE_GROUPS) {
      for (const link of group.links) {
        expect(link.url).toMatch(/^https:\/\/bginfo\.eu\//);
      }
    }
  });

  it('внутри группы нет повторяющихся ссылок', () => {
    for (const group of SERVICE_GROUPS) {
      const urls = group.links.map((link) => link.url);
      expect(new Set(urls).size).toBe(urls.length);
    }
  });

  it('id групп уникальны — используются как React key', () => {
    const ids = SERVICE_GROUPS.map((group) => group.id);
    expect(new Set(ids).size).toBe(ids.length);
  });
});

describe('SERVICE_CTA', () => {
  it('обе ссылки ведут на bginfo.eu по https', () => {
    expect(SERVICE_CTA.prices.url).toMatch(/^https:\/\/bginfo\.eu\//);
    expect(SERVICE_CTA.contact.url).toMatch(/^https:\/\/bginfo\.eu\//);
  });
});
