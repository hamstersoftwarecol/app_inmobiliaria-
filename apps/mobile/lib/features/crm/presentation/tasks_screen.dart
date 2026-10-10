import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/l10n/app_localizations.dart';
import '../../../core/l10n/locale_controller.dart';
import '../../../core/router/app_router.dart';
import '../../../core/util/format.dart';
import '../../../shared/widgets/async_view.dart';
import '../data/crm_repository.dart';

class TasksScreen extends StatelessWidget {
  const TasksScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return DefaultTabController(
      length: 3,
      child: Scaffold(
        appBar: AppBar(
          title: Text(l10n.t('tasks')),
          bottom: TabBar(
            labelColor: Colors.white,
            unselectedLabelColor: Colors.white70,
            tabs: [
              Tab(text: l10n.t('task_open')),
              Tab(text: l10n.t('task_overdue')),
              Tab(text: l10n.t('task_completed')),
            ],
          ),
        ),
        body: const TabBarView(
          children: [
            _Tasks(state: 'open'),
            _Tasks(state: 'overdue'),
            _Tasks(state: 'completed'),
          ],
        ),
      ),
    );
  }
}

class _Tasks extends ConsumerWidget {
  const _Tasks({required this.state});

  final String state;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final lang = ref.watch(localeControllerProvider).languageCode;
    final tasks = ref.watch(tasksProvider(state));
    return RefreshIndicator(
      onRefresh: () => ref.refresh(tasksProvider(state).future),
      child: AsyncView(
        value: tasks,
        onRetry: () => ref.invalidate(tasksProvider(state)),
        data: (page) => page.items.isEmpty
            ? ListView(
                children: [
                  const SizedBox(height: 80),
                  EmptyState(message: l10n.t('noTasks'), icon: Icons.task_alt),
                ],
              )
            : ListView(
                children: [
                  for (final t in page.items)
                    CheckboxListTile(
                      value: t.completedAt != null,
                      title: Text(t.title),
                      subtitle: Text(
                        [formatDateTime(t.dueAt, lang), ?t.leadName].join(' · '),
                        style: t.isOverdue ? TextStyle(color: Theme.of(context).colorScheme.error) : null,
                      ),
                      secondary: t.leadId == null
                          ? null
                          : IconButton(
                              tooltip: t.leadName,
                              icon: const Icon(Icons.person_outline),
                              onPressed: () => context.push(Routes.lead(t.leadId!)),
                            ),
                      onChanged: (v) async {
                        try {
                          await ref.read(crmRepositoryProvider).setTaskCompleted(t.id, v ?? false);
                        } catch (e) {
                          if (context.mounted) showError(context, e);
                        }
                        ref.invalidate(tasksProvider);
                      },
                    ),
                ],
              ),
      ),
    );
  }
}
