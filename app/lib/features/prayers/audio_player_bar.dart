import 'package:flutter/material.dart';
import 'package:just_audio/just_audio.dart';

import '../../core/theme/app_theme.dart';
import '../../core/widgets/common.dart';

/// Player de áudio para orações guiadas (streaming com buffer).
class AudioPlayerBar extends StatefulWidget {
  const AudioPlayerBar({super.key, required this.url, required this.title});

  final String url;
  final String title;

  @override
  State<AudioPlayerBar> createState() => _AudioPlayerBarState();
}

class _AudioPlayerBarState extends State<AudioPlayerBar> {
  final _player = AudioPlayer();
  bool _ready = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final uri = Uri.tryParse(widget.url);
    if (uri == null || uri.scheme != 'https' && !uri.host.contains('10.0.2.2') && uri.host != 'localhost') {
      setState(() => _error = 'Áudio indisponível.');
      return;
    }
    try {
      await _player.setUrl(widget.url);
      if (mounted) setState(() => _ready = true);
    } catch (_) {
      if (mounted) setState(() => _error = 'Não foi possível carregar o áudio.');
    }
  }

  @override
  void dispose() {
    _player.dispose();
    super.dispose();
  }

  String _fmt(Duration d) => '${d.inMinutes.remainder(60).toString().padLeft(2, '0')}:${d.inSeconds.remainder(60).toString().padLeft(2, '0')}';

  @override
  Widget build(BuildContext context) {
    final p = context.sancta;
    if (_error != null) {
      return SanctaTile(icon: Icons.headphones_rounded, title: 'Oração guiada', subtitle: _error);
    }

    return Material(
      color: p.surface,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(SanctaRadius.card),
        side: p.isDark ? BorderSide.none : BorderSide(color: p.outline),
      ),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(10, 10, 16, 6),
        child: StreamBuilder<PlayerState>(
          stream: _player.playerStateStream,
          builder: (context, snapshot) {
            final playing = snapshot.data?.playing ?? false;
            final loading = !_ready || snapshot.data?.processingState == ProcessingState.buffering;
            return Row(
              children: [
                if (loading && !playing)
                  const SizedBox(
                    width: 56,
                    height: 56,
                    child: Padding(padding: EdgeInsets.all(16), child: CircularProgressIndicator(strokeWidth: 2.4)),
                  )
                else
                  PlayButton(
                    size: 52,
                    icon: playing ? Icons.pause_rounded : Icons.play_arrow_rounded,
                    semanticLabel: playing ? 'Pausar oração guiada' : 'Ouvir oração guiada',
                    onPressed: () => playing ? _player.pause() : _player.play(),
                  ),
                const SizedBox(width: 6),
                Expanded(
                  child: StreamBuilder<Duration>(
                    stream: _player.positionStream,
                    builder: (context, pos) {
                      final total = _player.duration ?? Duration.zero;
                      final position = pos.data ?? Duration.zero;
                      final max = total.inMilliseconds <= 0 ? 1.0 : total.inMilliseconds.toDouble();
                      return Column(
                        mainAxisSize: MainAxisSize.min,
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Padding(
                            padding: const EdgeInsets.only(left: 8),
                            child: Row(
                              children: [
                                Expanded(child: Text('Oração guiada', style: context.text.titleSmall)),
                                Text('${_fmt(position)} / ${_fmt(total)}', style: context.text.bodySmall),
                              ],
                            ),
                          ),
                          SliderTheme(
                            data: SliderTheme.of(context).copyWith(trackHeight: 4, thumbShape: const RoundSliderThumbShape(enabledThumbRadius: 7)),
                            child: Slider(
                              value: position.inMilliseconds.clamp(0, max.toInt()).toDouble(),
                              max: max,
                              semanticFormatterCallback: (_) => '${_fmt(position)} de ${_fmt(total)}',
                              onChanged: _ready ? (v) => _player.seek(Duration(milliseconds: v.round())) : null,
                            ),
                          ),
                        ],
                      );
                    },
                  ),
                ),
              ],
            );
          },
        ),
      ),
    );
  }
}
