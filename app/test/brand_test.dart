import 'package:flutter/foundation.dart' show LicenseRegistry;
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sancta/core/brand/brand.dart';

/// Path do arco e da cruz no master SVG (brand/source/portal.js com escala 1), a partir das constantes Dart.
String _masterArch(PortalGeometry g) {
  const r = PortalGeometry.outerRadius;
  const i = PortalGeometry.innerRadius;
  String n(double v) => v == v.roundToDouble() ? v.toInt().toString() : v.toString();
  final h = g.height;
  return 'M0 ${n(h)}V${n(h - g.outerCenterY)}A${n(r)} ${n(r)} 0 0 1 ${n(2 * r)} ${n(h - g.outerCenterY)}V${n(h)}'
      'H${n(r + i)}V${n(h - g.innerCenterY)}A${n(i)} ${n(i)} 0 0 0 ${n(r - i)} ${n(h - g.innerCenterY)}V${n(h)}Z';
}

/// Compara retângulos com tolerância (a conversão de arcos em cônicas gera frações de pixel).
void _rect(Rect actual, Rect expected, [double tol = 0.5]) {
  expect(actual.left, closeTo(expected.left, tol));
  expect(actual.top, closeTo(expected.top, tol));
  expect(actual.right, closeTo(expected.right, tol));
  expect(actual.bottom, closeTo(expected.bottom, tol));
}

Widget _app(Widget child) => MaterialApp(
  debugShowCheckedModeBanner: false,
  home: Scaffold(body: Center(child: child)),
);

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  group('PortalGeometry', () {
    test('proporções e medidas derivadas do "n" da Figtree Bold', () {
      expect(PortalGeometry.width, 486);
      expect(PortalGeometry.stem, PortalGeometry.outerRadius - PortalGeometry.innerRadius); // 243 − 111 = 132
      expect(PortalGeometry.letter.height, 512);
      expect(PortalGeometry.door.height, 666);
      expect(PortalGeometry.letter.openingTop, 395);
      expect(PortalGeometry.letter.aspectRatio, closeTo(486 / 512, 1e-9));
      expect(PortalGeometry.of(PortalShape.door), same(PortalGeometry.door));
    });

    test('paths ocupam a caixa certa (escala 1)', () {
      const s = Size(486, 512);
      final g = PortalGeometry.letter;
      _rect(g.arch(s).getBounds(), const Rect.fromLTRB(0, 0, 486, 512));
      _rect(g.outline(s).getBounds(), const Rect.fromLTRB(0, 0, 486, 512));
      _rect(g.opening(s).getBounds(), const Rect.fromLTRB(132, 117, 354, 512));
      _rect(g.crossPath(s).getBounds(), const Rect.fromLTRB(168, 195, 318, 457));
      expect(g.stemWidth(s), 132);
      // a abertura não pertence ao arco; a haste sim
      expect(g.arch(s).contains(const Offset(243, 300)), isFalse);
      expect(g.arch(s).contains(const Offset(60, 400)), isTrue);
      expect(g.centerline(s).computeMetrics().single.length, greaterThan(1000));
    });

    test('encaixe "contain" centralizado em qualquer tamanho', () {
      final g = PortalGeometry.door;
      final b = g.bounds(const Size(400, 400));
      expect(b.height, closeTo(400, 1e-9));
      expect(b.width, closeTo(400 * 486 / 666, 1e-9));
      expect(b.center.dx, closeTo(200, 1e-9));
      expect(g.arch(const Size(400, 400)).getBounds().left, closeTo(b.left, 1e-6));
    });

    test('Dart e master SVG usam as mesmas coordenadas', () async {
      final svg = await rootBundle.loadString('assets/brand/portal_azul.svg');
      expect(svg, contains(_masterArch(PortalGeometry.letter)));
      expect(svg, contains('M214 195L272 195L272 247L318 247L318 305L272 305L272 457L214 457L214 305L168 305L168 247L214 247Z'));
      expect(svg, contains('#1F3D9C'));
      expect(svg, contains('#E3B65B'));
    });
  });

  group('ArchClipper', () {
    test('topo em semicírculo com raio = largura/2', () {
      final p = ArchClipper.archPath(const Size(300, 200));
      _rect(p.getBounds(), const Rect.fromLTRB(0, 0, 300, 200));
      expect(p.contains(const Offset(150, 2)), isTrue); // topo do arco
      expect(p.contains(const Offset(5, 5)), isFalse); // canto recortado
      expect(p.contains(const Offset(2, 198)), isTrue); // canto inferior reto
    });

    test('cantos inferiores arredondados e moldura baixa não quebram', () {
      final p = ArchClipper.archPath(const Size(300, 200), bottomRadius: 20);
      expect(p.contains(const Offset(1, 199)), isFalse);
      _rect(ArchClipper.archPath(const Size(300, 60)).getBounds(), const Rect.fromLTRB(0, 0, 300, 60));
      _rect(ArchClipper.archPath(Size.zero).getBounds(), Rect.zero);
    });
  });

  group('SkyArt', () {
    test('céu por horário', () {
      expect(SkyArt.sceneFor(DateTime(2026, 10, 4, 6)), SkyScene.aurora);
      expect(SkyArt.sceneFor(DateTime(2026, 10, 4, 13)), SkyScene.luz);
      expect(SkyArt.sceneFor(DateTime(2026, 10, 4, 17, 30)), SkyScene.entardecer);
      expect(SkyArt.sceneFor(DateTime(2026, 10, 4, 22)), SkyScene.noite);
      expect(SkyArt.sceneFor(DateTime(2026, 10, 4, 3)), SkyScene.noite);
      expect(SkyArt.assetFor(SkyScene.noite, portrait: true), 'assets/brand/ceu_noite_retrato.svg');
    });

    testWidgets('todas as cenas pintam em paisagem e retrato, dentro da moldura', (tester) async {
      for (final scene in SkyScene.values) {
        for (final ratio in [3 / 2, 3 / 4]) {
          await tester.pumpWidget(
            _app(
              SizedBox(
                width: 240,
                child: ArchFrame(
                  aspectRatio: ratio,
                  bottomRadius: 12,
                  borderColor: BrandColors.ouro,
                  child: SkyArt(scene: scene),
                ),
              ),
            ),
          );
          expect(tester.takeException(), isNull);
          expect(find.byType(SkyArt), findsOneWidget);
        }
      }
      await tester.pumpWidget(_app(SizedBox(width: 120, height: 160, child: SkyArt.forTime(DateTime(2026, 10, 4, 20)))));
      expect(tester.takeException(), isNull);
    });
  });

  group('PortalMark', () {
    testWidgets('variantes renderizam com a proporção certa', (tester) async {
      await tester.pumpWidget(
        _app(
          const Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              PortalMark(height: 64),
              PortalMark.pergaminho(height: 32, shape: PortalShape.door),
              PortalMark.mono(color: BrandColors.tinta, height: 16, semanticLabel: null),
              PortalMark(height: 48, openingColor: BrandColors.ouro),
            ],
          ),
        ),
      );
      expect(tester.takeException(), isNull);
      expect(find.byType(PortalMark), findsNWidgets(4));
      final size = tester.getSize(find.byType(PortalMark).first);
      expect(size.height, 64);
      expect(size.width, closeTo(64 * 486 / 512, 1e-6));
      final door = tester.getSize(find.byType(PortalMark).at(1));
      expect(door.width, closeTo(32 * 486 / 666, 1e-6));
      expect(find.bySemanticsLabel('Sancta'), findsWidgets);
    });

    test('PortalPainter repinta só quando muda', () {
      const a = PortalPainter(geometry: PortalGeometry.letter, arcColor: BrandColors.azulManto, crossColor: BrandColors.ouro);
      const b = PortalPainter(geometry: PortalGeometry.letter, arcColor: BrandColors.azulManto, crossColor: BrandColors.ouro, crossOpacity: 0.5);
      expect(a.shouldRepaint(a), isFalse);
      expect(b.shouldRepaint(a), isTrue);
    });
  });

  group('SanctaWordmark', () {
    testWidgets('todas as variantes carregam o SVG', (tester) async {
      await tester.runAsync(() async {
        await tester.pumpWidget(
          _app(
            Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                for (final v in WordmarkVariant.values) SanctaWordmark(variant: v, height: 24),
                const SanctaWordmark(color: Colors.teal, height: 20),
              ],
            ),
          ),
        );
        await Future<void>.delayed(const Duration(milliseconds: 300));
      });
      await tester.pump();
      expect(tester.takeException(), isNull);
      expect(find.byType(SvgPicture), findsNWidgets(WordmarkVariant.values.length + 1));
      final size = tester.getSize(find.byType(SanctaWordmark).first);
      expect(size.height, 24);
      expect(size.width, closeTo(24 * SanctaWordmark.aspectRatio, 0.01));
    });

    test('assets da marca existem no bundle', () async {
      final paths = [
        for (final v in WordmarkVariant.values) SanctaWordmark.assetFor(v),
        for (final s in SkyScene.values) SkyArt.assetFor(s),
        for (final s in SkyScene.values) SkyArt.assetFor(s, portrait: true),
        'assets/brand/portal_pergaminho.svg',
        'assets/brand/portal_mono_branco.svg',
        'assets/brand/postura_de_pe.svg',
        'assets/images/sancta_mark.png',
        'assets/images/de_pe.png',
        'assets/images/de_joelhos.png',
        'assets/images/maos_postas.png',
      ];
      for (final p in paths) {
        final data = await rootBundle.load(p);
        expect(data.lengthInBytes, greaterThan(100), reason: p);
      }
    });
  });

  test('licenças OFL das fontes são registradas', () async {
    LicenseRegistry.reset(); // isola o teste das licenças do próprio Flutter
    registerBrandFontLicenses();
    registerBrandFontLicenses(); // idempotente
    final packages = <String>{};
    await for (final entry in LicenseRegistry.licenses) {
      packages.addAll(entry.packages);
    }
    expect(packages, containsAll(['Figtree', 'EB Garamond']));
    expect(BrandFonts.figtree, 'Figtree');
  });
}
