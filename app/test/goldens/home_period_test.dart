// Tela Hoje: o cartão principal usa a oração do período escolhida no servidor (`/home` → `period_prayers`,
// painel → Configurações → Aplicativo), com a heurística local como reserva para servidores antigos.
import 'package:flutter_test/flutter_test.dart';

import 'fixtures.dart';
import 'harness.dart';

void main() {
  testWidgets('cartão do período mostra a oração escolhida no painel', (tester) async {
    // 8h30 (manhã): o painel escolheu o Santo Terço para a manhã.
    final home = homeJson()..['period_prayers'] = {'morning': prayersJson[1], 'afternoon': null, 'night': null};
    await pumpApp(tester, home: home);

    expect(find.text('Santo Terço · 20 min'), findsOneWidget);
    expect(find.text('Oração da manhã'), findsWidgets, reason: 'o título do cartão continua sendo o do período');
  });

  testWidgets('servidor antigo (sem period_prayers): heurística pelos destaques', (tester) async {
    final home = homeJson()..remove('period_prayers');
    await pumpApp(tester, home: home);

    // "Oração da manhã" (destaque) é a oração do período: o subtítulo traz a duração e o Evangelho.
    expect(find.text('8 min, com o Evangelho do dia'), findsOneWidget);
  });

  testWidgets('escolha do servidor nula para o período: heurística pelos destaques', (tester) async {
    final home = homeJson()..['period_prayers'] = {'morning': null, 'afternoon': prayersJson[1], 'night': null};
    await pumpApp(tester, home: home);

    expect(find.text('8 min, com o Evangelho do dia'), findsOneWidget);
    expect(find.text('Santo Terço · 20 min'), findsNothing);
  });
}
