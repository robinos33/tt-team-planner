/**
 * Équipe complète exigée en régional (assets/js/team-check.js).
 */
let completeness;

beforeAll(async () => {
  await import('../../assets/js/team-check.js');
  completeness = window.TTPTeamCheck.completeness;
});

const regional = { code: 'T1', level: 'R2', full_team_required: true };
const departmental = { code: 'T5', level: 'D1', full_team_required: false };

describe('TTPTeamCheck.completeness', () => {
  test('ne dit rien pour une équipe complète', () => {
    expect(completeness(regional, 4, '2026-11-14', '2026-11-13')).toBeNull();
  });

  test('ne dit rien hors régional, même à 3', () => {
    expect(completeness(departmental, 3, '2026-11-14', '2026-11-13')).toBeNull();
    expect(completeness({ code: 'T9' }, 3, '2026-11-14', '2026-11-13')).toBeNull();
  });

  test('reste discret quand la journée est à plus de 6 jours', () => {
    expect(completeness(regional, 3, '2026-11-14', '2026-11-07')).toBe('notice');
  });

  test('alerte à partir de 6 jours avant la journée', () => {
    expect(completeness(regional, 3, '2026-11-14', '2026-11-08')).toBe('alert');
    expect(completeness(regional, 0, '2026-11-14', '2026-11-14')).toBe('alert');
  });

  test('alerte encore une fois la journée passée', () => {
    expect(completeness(regional, 3, '2026-11-14', '2026-11-20')).toBe('alert');
  });

  test('reste discret sans date de journée', () => {
    expect(completeness(regional, 3, '', '2026-11-13')).toBe('notice');
  });
});
