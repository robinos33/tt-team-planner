/**
 * SMS pré-rédigés (assets/js/sms.js).
 */
let sms;

beforeAll(async () => {
  await import('../../assets/js/sms.js');
  sms = window.TTPSms;
});

describe('body', () => {
  test('remplace chaque variable par sa valeur', () => {
    const text = sms.body('{prenom} {nom} joue en {equipe} à la J{journee} ({date})', {
      prenom: 'Camille', nom: 'MARTIN', equipe: 'Équipe 1', journee: 3, date: '2026-11-08'
    });
    expect(text).toBe('Camille MARTIN joue en Équipe 1 à la J3 (2026-11-08)');
  });

  test('remplace toutes les occurrences d’une variable', () => {
    expect(sms.body('{prenom}, oui {prenom} !', { prenom: 'Léa' })).toBe('Léa, oui Léa !');
  });

  test('une variable sans valeur devient vide', () => {
    expect(sms.body('Bonjour {prenom} {nom}', { prenom: 'Léa' })).toBe('Bonjour Léa ');
  });

  test('une variable inconnue reste visible', () => {
    expect(sms.body('Rendez-vous à {heure}', {})).toBe('Rendez-vous à {heure}');
  });

  test('un modèle absent donne un texte vide', () => {
    expect(sms.body(undefined, { prenom: 'Léa' })).toBe('');
  });
});

describe('href', () => {
  test('retire les espaces du numéro et encode le texte', () => {
    expect(sms.href('06 12 34 56 78', 'Salut & à+')).toBe('sms:0612345678?body=Salut%20%26%20%C3%A0%2B');
  });
});
