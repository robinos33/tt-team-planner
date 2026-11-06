/**
 * Modèle de l'image de partage des compos (assets/js/share-image.js).
 * Le dessin (paint) n'est pas testé ici : jsdom n'a pas de Canvas 2D.
 */
let buildModel;

beforeAll(async () => {
  await import('../../assets/js/roster.js');
  await import('../../assets/js/team-check.js');
  await import('../../assets/js/share-image.js');
  buildModel = window.TTPShareImage.buildModel;
});

const LABELS = { rule3: '⚠ Limite J2', min_points: '⚠ Points min.', foreign: '⚠ 2e étranger', forfeit: '⚠ Forfait' };

function player(id, extra) {
  return Object.assign({ id, first_name: 'P' + id, last_name: 'NOM', ranking: 1000 + id, is_active: true }, extra);
}

function slot(team, num, pid) {
  return { team_code: team, slot_number: num, player_id: pid, round: 3 };
}

function avail(pid, status) {
  return { player_id: pid, phase: 1, round: 3, status };
}

function input(over) {
  return Object.assign({
    clubName: 'US Talence TT',
    phase: 1,
    round: 3,
    date: '2026-11-08',
    generatedAt: new Date('2026-11-05T14:32:00'),
    teams: [{ code: 'E1', name: 'Équipe 1', level: 'R1', color: '#f00' }, { code: 'E2', name: 'Équipe 2' }],
    compositions: [],
    players: [],
    availabilities: [],
    contexts: { E1: { burnage: {}, validated: false }, E2: { burnage: {}, validated: false } },
    forfeits: {},
    burnLabels: LABELS
  }, over);
}

const fourPlayers = (team, from) => [1, 2, 3, 4].map((n) => slot(team, n, from + n));
const players = (ids, extra) => ids.map((id) => player(id, extra));
const allAvailable = (ids) => ids.map((id) => avail(id, 'available'));

describe('buildModel', () => {
  test('une carte par équipe, dans l’ordre de la configuration, 4 slots ordonnés', () => {
    const m = buildModel(input({
      compositions: [slot('E2', 2, 7), slot('E1', 3, 1), slot('E1', 1, 2)],
      players: players([1, 2, 7]),
      availabilities: allAvailable([1, 2, 7])
    }));

    expect(m.teams.map((t) => t.name)).toEqual(['Équipe 1', 'Équipe 2']);
    expect(m.teams[0].slots.map((s) => (s.empty ? null : s.name))).toEqual(['P2 NOM', null, 'P1 NOM', null]);
    expect(m.teams[1].slots.map((s) => s.empty)).toEqual([true, false, true, true]);
  });

  test('un slot sans joueur est vide, même s’il existe en base', () => {
    const m = buildModel(input({ compositions: [{ team_code: 'E1', slot_number: 1, player_id: null }] }));
    expect(m.teams[0].slots[0]).toEqual({ num: 1, empty: true });
  });

  test('le brûlage affiché est celui de l’équipe du joueur, pas d’une autre', () => {
    const m = buildModel(input({
      compositions: [slot('E1', 1, 1), slot('E2', 1, 2)],
      players: players([1, 2]),
      availabilities: allAvailable([1, 2]),
      contexts: {
        // Le joueur 1 serait brûlé en E2, mais il joue en E1.
        E1: { burnage: { 2: { burned: true, reason: 'rule3' } }, validated: false },
        E2: { burnage: { 1: { burned: true, reason: 'rule3' }, 2: { burned: true, reason: 'min_points' } }, validated: false }
      }
    }));

    expect(m.teams[0].slots[0].danger).toBe(false);
    expect(m.teams[1].slots[0].danger).toBe(true);
    expect(m.teams[1].slots[0].chips).toContainEqual({ label: '⚠ Points min.', tone: 'danger' });
  });

  test('indispo et brûlé mettent la ligne en défaut ; incertain et non renseigné non', () => {
    const m = buildModel(input({
      compositions: fourPlayers('E1', 0),
      players: players([1, 2, 3, 4]),
      availabilities: [avail(1, 'unavailable'), avail(2, 'uncertain'), avail(4, 'available')],
      contexts: { E1: { burnage: { 4: { burned: true, reason: 'rule2' } }, validated: false }, E2: { burnage: {}, validated: false } }
    }));
    const [indispo, incertain, inconnu, brule] = m.teams[0].slots;

    expect(indispo).toMatchObject({ danger: true, chips: [{ label: 'Indispo', tone: 'danger' }] });
    expect(incertain).toMatchObject({ danger: false, chips: [{ label: 'Incertain', tone: 'warn' }] });
    expect(inconnu).toMatchObject({ danger: false, chips: [{ label: 'Non renseigné', tone: 'neutral' }] });
    expect(brule).toMatchObject({ danger: true, chips: [{ label: '🔥 Brûlé', tone: 'danger' }] });
  });

  test('un joueur dispo et sans statut n’a aucun badge', () => {
    const m = buildModel(input({ compositions: [slot('E1', 1, 1)], players: [player(1)], availabilities: [avail(1, 'available')] }));
    expect(m.teams[0].slots[0].chips).toEqual([]);
  });

  test('la dispo d’une autre journée ne compte pas', () => {
    const m = buildModel(input({
      compositions: [slot('E1', 1, 1)],
      players: [player(1)],
      availabilities: [{ player_id: 1, phase: 1, round: 4, status: 'unavailable' }]
    }));
    expect(m.teams[0].slots[0].chips).toEqual([{ label: 'Non renseigné', tone: 'neutral' }]);
  });

  test('étranger, mutation et capitaine reprennent les libellés de l’app', () => {
    const m = buildModel(input({
      compositions: [slot('E1', 1, 1)],
      players: [player(1, { is_foreign: true, is_mutation: true, is_captain: true })],
      availabilities: [avail(1, 'available')]
    }));
    expect(m.teams[0].slots[0].chips.map((c) => c.label)).toEqual(['E', 'Mut.', '© Cap.']);
  });

  test('statut d’équipe : forfait, validée, complète, incomplète', () => {
    const m = buildModel(input({
      teams: [{ code: 'A' }, { code: 'B' }, { code: 'C' }, { code: 'D' }],
      compositions: [...fourPlayers('B', 0), ...fourPlayers('C', 10), slot('D', 1, 21)],
      players: players([1, 2, 3, 4, 11, 12, 13, 14, 21]),
      contexts: {
        A: { burnage: {}, validated: false },
        B: { burnage: {}, validated: true },
        C: { burnage: {}, validated: false },
        D: { burnage: {}, validated: false }
      },
      forfeits: { A: true }
    }));

    expect(m.teams.map((t) => t.status.label)).toEqual(['Forfait', '✓ Validée', 'Complète', 'Incomplète 1/4']);
  });

  test('équipe régionale incomplète dans la semaine de la journée : rencontre perdue', () => {
    const m = buildModel(input({
      teams: [{ code: 'R', full_team_required: true }, { code: 'D', full_team_required: false }],
      compositions: [slot('R', 1, 1), slot('R', 2, 2), slot('R', 3, 3), slot('D', 1, 4)],
      players: players([1, 2, 3, 4]),
      contexts: { R: { burnage: {}, validated: false }, D: { burnage: {}, validated: false } }
    }));

    expect(m.teams.map((t) => t.status)).toEqual([
      { label: 'Incomplète 3/4 · rencontre perdue', tone: 'danger' },
      { label: 'Incomplète 1/4', tone: 'warn' }
    ]);
  });

  test('équipe régionale incomplète, journée lointaine : simple incomplète', () => {
    const m = buildModel(input({
      date: '2026-11-22',
      teams: [{ code: 'R', full_team_required: true }],
      compositions: [slot('R', 1, 1)],
      players: players([1]),
      contexts: { R: { burnage: {}, validated: false } }
    }));

    expect(m.teams[0].status).toEqual({ label: 'Incomplète 1/4', tone: 'warn' });
  });

  test('le compteur ignore les équipes forfait et les signale', () => {
    const m = buildModel(input({
      teams: [{ code: 'A' }, { code: 'B' }, { code: 'C' }],
      compositions: fourPlayers('B', 0),
      players: players([1, 2, 3, 4]),
      contexts: {},
      forfeits: { A: true }
    }));
    expect(m.counter).toEqual({ label: '1/2 compos · 1 forfait', tone: 'warn' });
  });

  test('le compteur passe au vert quand toutes les compos sont complètes', () => {
    const m = buildModel(input({
      teams: [{ code: 'A' }],
      compositions: fourPlayers('A', 0),
      players: players([1, 2, 3, 4])
    }));
    expect(m.counter).toEqual({ label: '1/1 compos', tone: 'ok' });
  });

  test('une équipe dont le contexte n’a pas pu être chargé est marquée non vérifiée', () => {
    const m = buildModel(input({
      contexts: { E1: { burnage: null, validated: false }, E2: { burnage: {}, validated: false } }
    }));
    expect(m.teams.map((t) => t.unverified)).toEqual([true, false]);
  });

  test('sans la liste des forfaits, toutes les équipes sont non vérifiées', () => {
    const m = buildModel(input({ forfeits: null }));
    expect(m.teams.every((t) => t.unverified)).toBe(true);
  });

  test('remplaçants : joueurs actifs, dispos et placés nulle part, du mieux classé au moins bien', () => {
    const m = buildModel(input({
      compositions: [slot('E1', 1, 1)],
      players: [
        player(1),                                       // placé
        player(2, { ranking: 900 }),
        player(3, { ranking: 1500 }),
        player(4),                                       // indispo
        player(5),                                       // dispo non renseignée
        player(6, { is_active: false })                  // hors effectif
      ],
      availabilities: [avail(1, 'available'), avail(2, 'available'), avail(3, 'available'), avail(4, 'unavailable'), avail(6, 'available')]
    }));
    expect(m.bench).toEqual(['P3 NOM · 1500', 'P2 NOM · 900']);
  });

  test('en-tête : club, journée, phase et date en toutes lettres', () => {
    const m = buildModel(input());
    expect(m.title).toBe('US Talence TT');
    expect(m.subtitle).toBe('Journée 3 · Phase 1');
    expect(m.date).toBe('dimanche 8 novembre');
    expect(m.footer).toBe('Généré le 05/11/2026 à 14:32');
  });
});
