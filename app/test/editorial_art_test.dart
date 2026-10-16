import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sancta/core/brand/editorial_art.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  group('EditorialArt', () {
    test('mapeia orações por período para as capas correspondentes', () {
      expect(EditorialArt.forPrayer('Oração da manhã', 'oracao-da-manha'), EditorialArt.oracaoManha);
      expect(EditorialArt.forPrayer('Oração da noite', 'oracao-da-noite'), EditorialArt.oracaoNoite);
      expect(EditorialArt.forPrayer('Terço diário', 'terco-diario'), EditorialArt.jornadaRosario);
      expect(EditorialArt.forPrayer('Evangelho do dia', 'evangelho-do-dia'), EditorialArt.leituraBiblica);
    });

    test('mapeia jornadas marianas sem afetar outras jornadas', () {
      expect(EditorialArt.forJourney('Novena de Nossa Senhora Aparecida', 'novena-aparecida'), EditorialArt.jornadaRosario);
      expect(EditorialArt.forJourney('Plano de leitura dos Salmos', 'salmos-30-dias'), isNull);
    });

    test('mapeia somente os santos cobertos pelo kit', () {
      expect(EditorialArt.forSaint('São Carlo Acutis'), EditorialArt.carloAcutis);
      expect(EditorialArt.forSaint('Nossa Senhora Aparecida'), EditorialArt.nossaSenhoraAparecida);
      expect(EditorialArt.forSaint('São José'), EditorialArt.saoJose);
      expect(EditorialArt.forSaint('São Francisco de Assis'), EditorialArt.saoFrancisco);
      expect(EditorialArt.forSaint('Santa Teresinha'), isNull);
    });

    test('inclui e decodifica as artes WebP essenciais no bundle do app', () async {
      for (final path in [EditorialArt.oracaoManha, EditorialArt.acesso, EditorialArt.saoFrancisco]) {
        final data = await rootBundle.load(path);
        final bytes = data.buffer.asUint8List(data.offsetInBytes, data.lengthInBytes);
        final codec = await ui.instantiateImageCodec(bytes);
        final frame = await codec.getNextFrame();
        expect(frame.image.width, greaterThan(0), reason: path);
        frame.image.dispose();
        codec.dispose();
      }
    });

    testWidgets('renderiza uma arte local dentro de Image.asset', (tester) async {
      await tester.pumpWidget(MaterialApp(home: Image.asset(EditorialArt.oracaoManha, cacheWidth: 1200)));
      await tester.runAsync(() => Future<void>.delayed(const Duration(milliseconds: 500)));
      await tester.pump();
      expect(tester.widget<RawImage>(find.byType(RawImage)).image, isNotNull);
    });
  });
}
