/**
 * Effectif d'une journée rangé par disponibilité (assets/js/roster.js).
 */
let group;

beforeAll(async () => {
  await import('../../assets/js/roster.js');
  group = window.TTPRoster.group;
});

function player(id, extra) {
  return Object.assign({ id, first_name: 'P' + id, last_name: 'NOM', ranking: 1000 + id, is_active: true }, extra);
}

function slot(team, num, pid) {
  return { team_code: team, slot_number: num, player_id: pid };
}

function avail(pid, status, round) {
  return { player_id: pid, phase: 1, round: round || 3, status };
}

function input(over) {
  return Object.assign({
    phase: 1,
    round: 3,
    teams: [{ code: 'E1' }, { code: 'E2' }],
    players: [],
    availabilities: [],
    compositions: [],
    forfeits: {}
  }, over);
}

function ids(entries) {
  return entries.map((e) => e.player.id);
}

describe('TTPRoster.group', () => {
  test('range chaque joueur actif selon sa dispo de la journée', () => {
    const g = group(input({
      players: [player(1), player(2), player(3), player(4)],
      availabilities: [avail(1, 'available'), avail(2, 'uncertain'), avail(3, 'unavailable')]
    }));
    expect(ids(g.free)).toEqual([1]);
    expect(ids(g.uncertain)).toEqual([2]);
    expect(ids(g.unavailable)).toEqual([3]);
    expect(ids(g.unknown)).toEqual([4]);
  });

  test('ne lit que la dispo de la journée et de la phase demandées', () => {
    const g = group(input({
      players: [player(1), player(2)],
      availabilities: [avail(1, 'available', 2), Object.assign(avail(2, 'available'), { phase: 2 })]
    }));
    expect(ids(g.unknown)).toEqual([2, 1]);
    expect(g.available).toBe(0);
  });

  test('sépare les dispo placés des non placés, avec leur équipe', () => {
    const g = group(input({
      players: [player(1), player(2)],
      availabilities: [avail(1, 'available'), avail(2, 'available')],
      compositions: [slot('E2', 1, 2)]
    }));
    expect(ids(g.free)).toEqual([1]);
    expect(g.placed).toEqual([{ player: expect.objectContaining({ id: 2 }), team: 'E2' }]);
    expect(g.available).toBe(2);
  });

  test('donne aussi son équipe à un joueur placé mais indispo', () => {
    const g = group(input({
      players: [player(1)],
      availabilities: [avail(1, 'unavailable')],
      compositions: [slot('E1', 2, 1)]
    }));
    expect(g.unavailable).toEqual([{ player: expect.objectContaining({ id: 1 }), team: 'E1' }]);
  });

  test('ignore les joueurs retirés de l’effectif et les slots hors 1 à 4', () => {
    const g = group(input({
      players: [player(1, { is_active: false }), player(2)],
      availabilities: [avail(1, 'available'), avail(2, 'available')],
      compositions: [slot('E1', 5, 2)]
    }));
    expect(ids(g.free)).toEqual([2]);
    expect(g.placed).toEqual([]);
  });

  test('trie les non placés par classement, les placés par équipe puis classement', () => {
    const g = group(input({
      players: [player(1, { ranking: 500 }), player(2, { ranking: 900 }), player(3, { ranking: 700 }), player(4, { ranking: 1200 })],
      availabilities: [avail(1, 'available'), avail(2, 'available'), avail(3, 'available'), avail(4, 'available')],
      compositions: [slot('E2', 1, 4), slot('E1', 1, 1), slot('E1', 2, 3)]
    }));
    expect(ids(g.free)).toEqual([2]);
    expect(ids(g.placed)).toEqual([3, 1, 4]);
  });

  test('compte 4 places par équipe, hors équipes forfait', () => {
    expect(group(input()).places).toBe(8);
    expect(group(input({ forfeits: { E2: true } })).places).toBe(4);
    expect(group(input({ forfeits: null })).places).toBe(8);
  });
});
