import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';
import 'package:go_router/go_router.dart';
import 'package:latlong2/latlong.dart';

import '../../app/router.dart';
import '../../core/brand/brand.dart';
import '../../core/models/models.dart';
import '../../core/state/config_controller.dart';
import '../../core/state/content_providers.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/location.dart';
import '../../core/widgets/common.dart';

/// "350 m" abaixo de 1 km; "1,2 km" (vírgula decimal) acima.
String formatDistance(double km) {
  if (km < 1) return '${(km * 1000 / 10).round() * 10} m';
  return '${km.toStringAsFixed(km < 10 ? 1 : 0).replaceAll('.', ',')} km';
}

/// Aba **Igrejas**: lista (padrão, mais acessível) e mapa (OpenStreetMap — sem chave de API no app).
class ChurchesScreen extends ConsumerStatefulWidget {
  const ChurchesScreen({super.key});

  @override
  ConsumerState<ChurchesScreen> createState() => _ChurchesScreenState();
}

class _ChurchesScreenState extends ConsumerState<ChurchesScreen> {
  final _map = MapController();
  final _search = TextEditingController();
  LatLng? _me;
  String _query = '';
  bool _locating = false;
  bool _showMap = false;
  Church? _selected;

  ({double? lat, double? lng, String? q}) get _args => (lat: _me?.latitude, lng: _me?.longitude, q: _query.isEmpty ? null : _query);

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  Future<void> _locate() async {
    setState(() => _locating = true);
    try {
      final pos = await currentPosition(accuracy: LocationAccuracy.medium);
      if (!mounted) return;
      setState(() => _me = LatLng(pos.latitude, pos.longitude));
      if (_showMap) _map.move(_me!, 13);
    } on LocationFailure catch (e) {
      if (mounted) showMessage(context, e.message);
    } catch (_) {
      if (mounted) showMessage(context, 'Não foi possível obter a localização.');
    } finally {
      if (mounted) setState(() => _locating = false);
    }
  }

  void _suggest() {
    if (requireAccount(context, ref, reason: 'Entre para sugerir uma igreja.')) context.push('/sugerir-igreja');
  }

  @override
  Widget build(BuildContext context) {
    final churches = ref.watch(churchesProvider(_args));
    final p = context.sancta;

    return Scaffold(
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          ScreenTitle(
            'Igrejas',
            subtitle: _me != null ? 'Perto de você' : 'Missas, confissões e adoração',
            actions: [IconButton(tooltip: 'Sugerir igreja', icon: const Icon(Icons.add_location_alt_outlined), onPressed: _suggest)],
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 14, 16, 0),
            child: Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: _search,
                    textInputAction: TextInputAction.search,
                    decoration: const InputDecoration(hintText: 'Nome, bairro ou cidade', prefixIcon: Icon(Icons.search_rounded)),
                    onSubmitted: (v) => setState(() => _query = v.trim()),
                  ),
                ),
                const SizedBox(width: 10),
                SizedBox(
                  width: 54,
                  height: 54,
                  child: IconButton.filled(
                    tooltip: 'Igrejas perto de mim',
                    style: IconButton.styleFrom(
                      backgroundColor: _me != null ? p.primary : p.iconBg,
                      foregroundColor: _me != null ? p.onPrimary : p.primary,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(SanctaRadius.tile)),
                    ),
                    onPressed: _locating ? null : _locate,
                    icon: _locating
                        ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2))
                        : const Icon(Icons.my_location_rounded),
                  ),
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
            child: SegmentedButton<bool>(
              showSelectedIcon: false,
              segments: const [
                ButtonSegment(value: false, icon: Icon(Icons.view_list_rounded), label: Text('Lista')),
                ButtonSegment(value: true, icon: Icon(Icons.map_outlined), label: Text('Mapa')),
              ],
              selected: {_showMap},
              onSelectionChanged: (s) => setState(() => _showMap = s.first),
            ),
          ),
          Expanded(
            child: churches.when(
              skipLoadingOnReload: true,
              loading: () => const SkeletonTiles(count: 6, padding: EdgeInsets.fromLTRB(16, 10, 16, 16)),
              error: (e, _) => ErrorView(error: e, onRetry: () => ref.invalidate(churchesProvider(_args))),
              data: (items) => _showMap ? _mapView(context, items) : _listView(context, items),
            ),
          ),
        ],
      ),
    );
  }

  Widget _listView(BuildContext context, List<Church> items) {
    final showLocate = _me == null && _query.isEmpty;
    if (items.isEmpty) {
      return EmptyView(
        icon: Icons.church_outlined,
        title: 'Nenhuma igreja encontrada',
        message: 'Conhece uma igreja que não está no Sancta? Sugira para a comunidade.',
        action: OutlinedButton(onPressed: _suggest, child: const Text('Sugerir igreja')),
      );
    }
    return ListView.separated(
      padding: const EdgeInsets.fromLTRB(16, 10, 16, 24),
      itemCount: items.length + (showLocate ? 1 : 0),
      separatorBuilder: (_, _) => const SizedBox(height: 8),
      itemBuilder: (_, i) {
        if (showLocate && i == 0) {
          return SanctaTile(
            icon: Icons.near_me_outlined,
            title: 'Ver as igrejas mais próximas',
            subtitle: 'Use sua localização para ordenar por distância.',
            onTap: _locating ? null : _locate,
          );
        }
        return ChurchTile(church: items[i - (showLocate ? 1 : 0)]);
      },
    );
  }

  Widget _mapView(BuildContext context, List<Church> items) {
    final config = ref.watch(configValueProvider);
    final p = context.sancta;
    final located = items.where((c) => c.hasLocation).toList();

    Widget tiles = TileLayer(urlTemplate: p.isDark ? config.tilesDark : config.tilesLight, userAgentPackageName: 'com.maria.app_igreja', maxZoom: 19);
    if (p.isDark && config.mapDarkFilter) {
      // Inverte as cores do mapa claro para combinar com o tema escuro.
      tiles = ColorFiltered(colorFilter: const ColorFilter.matrix([-0.6, 0, 0, 0, 200, 0, -0.6, 0, 0, 205, 0, 0, -0.55, 0, 215, 0, 0, 0, 1, 0]), child: tiles);
    }

    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(SanctaRadius.card),
        child: Stack(
          children: [
            FlutterMap(
              mapController: _map,
              options: MapOptions(
                initialCenter: _me ?? (located.isNotEmpty ? LatLng(located.first.latitude!, located.first.longitude!) : const LatLng(-14.5, -51)),
                initialZoom: _me != null ? 13 : (located.isNotEmpty ? 11 : 4),
                onTap: (_, _) => setState(() => _selected = null),
              ),
              children: [
                tiles,
                MarkerLayer(
                  markers: [
                    if (_me != null)
                      Marker(
                        point: _me!,
                        width: 22,
                        height: 22,
                        child: Semantics(
                          label: 'Você está aqui',
                          child: Container(
                            decoration: BoxDecoration(
                              color: BrandColors.azulLuz,
                              shape: BoxShape.circle,
                              border: Border.all(color: Colors.white, width: 3),
                              boxShadow: const [BoxShadow(blurRadius: 6, color: Color(0x40000000))],
                            ),
                          ),
                        ),
                      ),
                    for (final c in located)
                      Marker(
                        point: LatLng(c.latitude!, c.longitude!),
                        width: 48,
                        height: 48,
                        child: _ChurchPin(church: c, selected: _selected?.id == c.id, onTap: () => setState(() => _selected = c)),
                      ),
                  ],
                ),
                RichAttributionWidget(attributions: [TextSourceAttribution(config.mapAttribution)]),
              ],
            ),
            if (_selected != null) Positioned(left: 10, right: 10, bottom: 10, child: ChurchTile(church: _selected!, elevated: true)),
          ],
        ),
      ),
    );
  }
}

class _ChurchPin extends StatelessWidget {
  const _ChurchPin({required this.church, required this.selected, required this.onTap});

  final Church church;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Semantics(
    button: true,
    selected: selected,
    label: church.name,
    excludeSemantics: true,
    child: GestureDetector(
      onTap: onTap,
      child: Center(
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 180),
          width: selected ? 42 : 34,
          height: selected ? 42 : 34,
          decoration: BoxDecoration(
            color: BrandColors.azulManto,
            shape: BoxShape.circle,
            border: Border.all(color: selected ? BrandColors.ouro : Colors.white, width: selected ? 3 : 2.5),
            boxShadow: const [BoxShadow(blurRadius: 8, offset: Offset(0, 2), color: Color(0x4D0E1A3A))],
          ),
          child: Icon(Icons.church_rounded, size: selected ? 20 : 17, color: BrandColors.pergaminho),
        ),
      ),
    ),
  );
}

/// Linha de igreja: próxima missa e distância.
class ChurchTile extends StatelessWidget {
  const ChurchTile({super.key, required this.church, this.elevated = false});

  final Church church;
  final bool elevated;

  @override
  Widget build(BuildContext context) {
    final p = context.sancta;
    final subtitle = [
      church.nextMass != null ? 'Missa: ${church.nextMass}' : church.address,
      if (church.distanceKm != null) formatDistance(church.distanceKm!),
    ].join(' · ');
    final tile = SanctaTile(
      onTap: () => context.push('/igreja/${church.id}'),
      icon: Icons.church_rounded,
      title: church.name,
      subtitle: subtitle,
      semanticLabel: church.isVerified ? 'Igreja verificada' : null,
      trailing: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (church.isVerified) Icon(Icons.verified_rounded, size: 18, color: p.primary),
          Icon(Icons.chevron_right_rounded, color: p.muted, size: 22),
        ],
      ),
    );
    if (!elevated) return tile;
    return DecoratedBox(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(SanctaRadius.tile),
        boxShadow: const [BoxShadow(blurRadius: 16, offset: Offset(0, 4), color: Color(0x330E1A3A))],
      ),
      child: tile,
    );
  }
}
