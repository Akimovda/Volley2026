window.volleyballConfig = {

  classic: {

    formats: [
      'game',
      'training',
      'training_game',
      'tournament',
      'camp'
    ],

    subtypes: {

      "4x4": {
        players_per_team: 4,
        min_players: 4,
        max_players: 8,
        positions: ['setter','outside','opposite']
      },

      "4x2": {
        players_per_team: 6,
        min_players: 6,
        max_players: 12,
        positions: ['setter','outside']
      },

      "5x1": {
        players_per_team: 6,
        min_players: 6,
        max_players: 12,
        positions: ['setter','outside','opposite','middle','libero']
      }

    }

  },

  beach: {

    formats: [
      'game',
      'training',
      'training_game',
      'coach_student',
      'tournament',
      'camp'
    ],

    subtypes: {

      "2x2": {
        players_per_team: 2,
        min_players: 4,
        max_players: 6,
        positions: []
      },

      "3x3": {
        players_per_team: 3,
        min_players: 6,
        max_players: 12,
        positions: []
      },

      "4x4": {
        players_per_team: 4,
        min_players: 8,
        max_players: 16,
        positions: []
      }

    }

  }

};
